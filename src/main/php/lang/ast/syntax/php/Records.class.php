<?php namespace lang\ast\syntax\php;

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
use lang\ast\{Code, Type, ArrayType};

class Records implements Extension {

  public static function inject(&$type, $name, $signature, $body) {
    $key= $name.'()';
    isset($type[$key]) || $type[$key]= new Method(['public'], $name, $signature, [new ReturnStatement($body)]);
  }

  public function setup($language, $emitter) {
    $language->stmt('record', function($parse, $token) {
      $type= $parse->scope->resolve($parse->token->value);
      $parse->forward();

      $comment= $parse->comment;
      $annotations= $parse->scope->annotations;
      $parse->comment= null;
      $parse->scope->annotations= [];
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
      $body= $this->typeBody($parse);
      $parse->expecting('}', 'record');

      if (isset($body['__construct()'])) {
        $parse->raise('Records cannot have a constructor, use __init()', 'record', $line);
      }

      $return= new RecordDeclaration([], $type, $components, $parent, $implements, $body, $annotations, $comment, $line);
      return $return;
    });

    $emitter->transform('record', function($codegen, $node) {
      $body= $node->body;
      $string= $object= $value= '';
      $constructor= new Method(['public'], '__construct', new Signature($node->components, null), []);
      foreach ($node->components as $c) {
        $l= $c->line;

        // Assigment inside constructor
        $r= new InstanceExpression(new Variable('this', $l), new Literal($c->name, $l), $l);
        $constructor->body[]= new Assignment($r, '=', new Variable($c->name, $l), $l);

        // Property declaration + accessor method
        $type= $c->variadic ? ($c->type ? new ArrayType($c->type) : new Type('array')) : $c->type;
        $body[]= new Property(['private'], $c->name, $type, null, [], null, $l);
        $body[]= new Method(['public'], $c->name, new Signature([], $type), [new ReturnStatement($r, $l)]);

        // Code for string representation, hashcode and comparison
        $string.= ', '.$c->name.'= ".\\util\\Objects::stringOf($this->'.$c->name.')."';
        $object.= ', $this->'.$c->name;
        $value.= ', $value->'.$c->name;
      }

      // Create constructor, inlining __init()
      if (isset($body['__init()'])) {
        foreach ($body['__init()']->body as $statement) {
          $constructor->body[]= $statement;
        }
        unset($body['__init()']);
      }
      $body['__construct()']= $constructor;

      // Implement lang.Value
      self::inject($body, 'toString', new Signature([], new Type('string')), new Code(
        '"'.strtr(substr($node->name, 1), '\\', '.').'('.substr($string, 2).')"'
      ));
      self::inject($body, 'hashCode', new Signature([], new Type('string')), new Code(
        'md5(\\util\\Objects::hashOf(["'.substr($node->name, 1).'"'.$object.']))'
      ));
      self::inject($body, 'compareTo', new Signature([new Parameter('value', null)], new Type('int')), new Code(
        '$value instanceof self ? \\util\\Objects::compare(['.substr($object, 2).'], ['.substr($value, 2).']) : 1'
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