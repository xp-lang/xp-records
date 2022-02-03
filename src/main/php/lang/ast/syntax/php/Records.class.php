<?php namespace lang\ast\syntax\php;

use lang\ast\Code;
use lang\ast\nodes\{
  Assignment,
  ClassDeclaration,
  InstanceExpression,
  Literal,
  Method,
  Parameter,
  Property,
  ReturnStatement,
  Signature,
  Variable
};
use lang\ast\syntax\Extension;
use lang\ast\types\{IsArray, IsLiteral};

class Records implements Extension {

  /** Injects a method if it does not exist yet */
  public static function inject(&$type, $name, $signature, $body) {
    $key= $name.'()';
    isset($type[$key]) || $type[$key]= new Method(['public'], $name, $signature, [new ReturnStatement($body)]);
  }

  public function setup($language, $emitter) {
    $language->stmt('record', function($parse, $token) {
      $type= $parse->scope->resolve($parse->token->value);
      $parse->forward();

      $comment= $parse->comment;
      $parse->comment= null;
      $line= $parse->token->line;

      $parse->expecting('(', 'record');
      $components= $this->parameters($parse, []);
      $parse->expecting(')', 'record');

      $parent= null;
      if ('extends' === $parse->token->value) {
        $parse->forward();
        $parent= $parse->scope->resolve($parse->token->value);
        $parse->forward();
      }

      $implements= [];
      if ('implements' === $parse->token->value) {
        $parse->forward();
        do {
          $implements[]= $parse->scope->resolve($parse->token->value);
          $parse->forward();
          if (',' === $parse->token->value) {
            $parse->forward();
          } else if ('{' === $parse->token->value) {
            break;
          } else {
            $parse->expecting(', or {', 'interfaces list');
          }
        } while (null !== $parse->token->value);
      }

      // Type body
      $parse->expecting('{', 'record');
      $body= $this->typeBody($parse, $type);
      $parse->expecting('}', 'record');

      if (isset($body['__construct()'])) {
        $parse->raise('Records cannot have a constructor, use init { }', 'record', $line);
      }

      return new RecordDeclaration([], $type, $components, $parent, $implements, $body, null, $comment, $line);
    });

    // Initializer block
    $language->body('init', function($parse, &$body, $meta, $modifiers, $holder) {
      $line= $parse->token->line;
      $parse->forward();

      $parse->expecting('{', 'initializer block');
      $statements= $this->statements($parse);
      $parse->expecting('}', 'initializer block');

      $body['<init>']= $statements;
    });

    $emitter->transform('record', function($codegen, $node) {
      $body= $node->body;
      $string= $object= $value= '';
      $signature= new Signature([], null);
      $constructor= new Method(['public'], '__construct', $signature, []);
      foreach ($node->components as $c) {
        $l= $c->line;

        $modifiers= $c->promote ?? 'private';
        $c->promote= null;
        $signature->parameters[]= $c;

        // Assigment inside constructor
        $r= new InstanceExpression(new Variable('this', $l), new Literal($c->name, $l), $l);
        $constructor->body[]= new Assignment($r, '=', new Variable($c->name, $l), $l);

        // Property declaration + accessor method
        $type= $c->variadic ? ($c->type ? new IsArray($c->type) : new IsLiteral('array')) : $c->type;
        $body[]= new Property([$modifiers], $c->name, $type, null, [], null, $l);
        $body[]= new Method(['public'], $c->name, new Signature([], $type), [new ReturnStatement($r, $l)]);

        // Code for string representation, hashcode and comparison
        $string.= ', '.$c->name.': ".\\util\\Objects::stringOf($this->'.$c->name.')."';
        $object.= ', $this->'.$c->name;
        $value.= ', $value->'.$c->name;
      }

      // Create constructor, inlining <init>. Also support deprecated __init() function
      if (isset($body['<init>'])) {
        foreach ($body['<init>'] as $statement) {
          $constructor->body[]= $statement;
        }
        unset($body['<init>']);
      } else if (isset($body['__init()'])) {
        trigger_error('Use init { } instead', E_USER_DEPRECATED);
        foreach ($body['__init()']->body as $statement) {
          $constructor->body[]= $statement;
        }
        unset($body['__init()']);
      }
      $body['__construct()']= $constructor;

      // Implement lang.Value
      self::inject($body, 'toString', new Signature([], new IsLiteral('string')), new Code(
        '"'.strtr(substr($node->name, 1), '\\', '.').'('.substr($string, 2).')"'
      ));
      self::inject($body, 'hashCode', new Signature([], new IsLiteral('string')), new Code(
        'md5(\\util\\Objects::hashOf(["'.substr($node->name, 1).'"'.$object.']))'
      ));
      self::inject($body, 'compareTo', new Signature([new Parameter('value', null)], new IsLiteral('int')), new Code(
        '$value instanceof self ? \\util\\Objects::compare(['.substr($object, 2).'], ['.substr($value, 2).']) : 1'
      ));

      // Add decomposition
      self::inject($body, '__invoke', new Signature([new Parameter('map', new IsLiteral('callable'), new Literal('null'))], null), new Code(
        'null === $map ? ['.substr($object, 2).'] : $map('.substr($object, 2).')'
      ));

      return new ClassDeclaration(
        ['final'],
        $node->name,
        $node->parent,
        array_merge(['\\lang\\Value'], $node->implements),
        $body,
        $node->annotations,
        $node->comment,
        $node->line
      );
    });
  }
}