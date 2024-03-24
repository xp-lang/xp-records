<?php namespace lang\ast\syntax\php;

use lang\ast\Code;
use lang\ast\nodes\{
  Assignment,
  ClassDeclaration,
  InstanceExpression,
  Literal,
  Method,
  NewClassExpression,
  Parameter,
  Property,
  ReturnStatement,
  Signature,
  Variable
};
use lang\ast\syntax\Extension;
use lang\ast\types\{IsArray, IsLiteral, IsValue, IsNullable};

class Records implements Extension {

  /** Injects a method if it does not exist yet */
  public static function inject(&$type, $name, $signature, $body) {
    $key= $name.'()';
    isset($type[$key]) || $type[$key]= new Method(['public'], $name, $signature, [new ReturnStatement($body)]);
  }

  /** Rewrites a given record and returns it body */
  public static function rewrite($node) {
    $body= $node->body;
    $string= $object= $value= '';
    $signature= new Signature([], null);
    $constructor= new Method(['public'], '__construct', $signature, []);
    foreach ($node->components as $c) {
      $l= $c->line;

      $modifiers= null === $c->promote ? ['private'] : explode(' ', $c->promote);
      $c->promote= null;
      $signature->parameters[]= $c;

      // Assigment inside constructor
      $r= new InstanceExpression(new Variable('this', $l), new Literal($c->name, $l), $l);
      $constructor->body[]= new Assignment($r, '=', new Variable($c->name, $l), $l);

      // Property declaration + accessor method
      $type= $c->variadic ? ($c->type ? new IsArray($c->type) : new IsLiteral('array')) : $c->type;
      $body[]= new Property($modifiers, $c->name, $type, null, [], null, $l);
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
    self::inject($body, '__invoke', new Signature([new Parameter('map', new IsNullable(new IsLiteral('callable')), new Literal('null'))], null), new Code(
      'null === $map ? ['.substr($object, 2).'] : $map('.substr($object, 2).')'
    ));

    return $body;
  }

  public function setup($language, $emitter) {

    // Anonymous records
    $new= $language->symbol('new')->nud;
    $language->prefix('new', 0, function($parse, $token) use($new) {
      if ('record' !== $parse->token->value) return $new($parse, $token);

      // Anonymous record syntax: `new record(...) { }`
      $parse->forward();

      $parse->expecting('(', 'new arguments');
      $arguments= $this->arguments($parse);
      $parse->expecting(')', 'new arguments');

      $parse->expecting('{', 'anonymous record');
      $body= $this->typeBody($parse, null);
      $parse->expecting('}', 'anonymous record');

      $components= [];
      foreach ($arguments as $name => $argument) {
        $components[]= new Parameter($name, null); // TODO: Infer type
      }

      $expr= new NewClassExpression(
        new RecordDeclaration([], '\\record', $components, null, [], $body),
        $arguments,
        $token->line
      );
      $expr->kind= 'newrecord';
      return $expr;
    });

    // Record declaration
    $language->stmt('record', function($parse, $token) {
      $comment= $parse->comment;
      $line= $parse->token->line;
      $parse->comment= null;

      $type= $this->type($parse, false);
      $parse->expecting('(', 'record');
      $components= $this->parameters($parse, []);
      $parse->expecting(')', 'record');

      $parent= null;
      if ('extends' === $parse->token->value) {
        $parse->forward();
        $parent= $this->type($parse, false);
      }

      $implements= [];
      if ('implements' === $parse->token->value) {
        $parse->forward();
        do {
          $implements[]= $this->type($parse, false);
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
      $body= $this->typeBody($parse, null); // BC: Pass deprecated holder
      $parse->expecting('}', 'record');

      if (isset($body['__construct()'])) {
        $parse->raise('Records cannot have a constructor, use init { }', 'record', $line);
      }

      return new RecordDeclaration([], $type, $components, $parent, $implements, $body, null, $comment, $line);
    });

    // Initializer block
    $language->body('init', function($parse, &$body, $meta, $modifiers) {
      $line= $parse->token->line;
      $parse->forward();

      $parse->expecting('{', 'initializer block');
      $statements= $this->statements($parse);
      $parse->expecting('}', 'initializer block');

      $body['<init>']= $statements;
    });

    $emitter->transform('newrecord', function($codegen, $node) {
      $node->kind= 'newclass';
      $node->definition= new ClassDeclaration(
        [],
        null,
        null,
        [new IsValue('\\lang\\Value')],
        self::rewrite($node->definition),
        [],
        null,
        $node->line
      );
      return $node;
    });

    $emitter->transform('record', function($codegen, $node) {
      return new ClassDeclaration(
        ['final'],
        $node->name,
        $node->parent,
        array_merge([new IsValue('\\lang\\Value')], $node->implements),
        self::rewrite($node),
        $node->annotations,
        $node->comment,
        $node->line
      );
    });
  }
}