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

class Records implements Extension {

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

      // Type body
      $parse->expecting('{', 'record');
      $body= $this->typeBody($parse);
      $parse->expecting('}', 'record');

      $return= new RecordDeclaration([], $type, $components, $body, $annotations, $comment, $line);
      return $return;
    });

    $emitter->transform('record', function($codegen, $node) {
      $body= $node->body;
      $string= $object= $value= '';

      $body[]= $constructor= new Method(['public'], '__construct', new Signature($node->components, null), []);
      foreach ($node->components as $c) {
        $l= $c->line;

        // Assigment inside constructor
        $r= new InstanceExpression(new Variable('this', $l), new Literal($c->name, $l), $l);
        $constructor->body[]= new Assignment($r, '=', new Variable($c->name, $l), $l);

        // Property declaration + accessor method
        $body[]= new Property(['private'], $c->name, $c->type, null, [], null, $l);
        $body[]= new Method(['public'], $c->name, new Signature([], $c->type), [new ReturnStatement($r, $l)]);

        // Code for string representation, hashcode and comparison
        $string.= ', '.$c->name.'= ".\\util\\Objects::stringOf($this->'.$c->name.')."';
        $object.= ', $this->'.$c->name;
        $value.= ', $value->'.$c->name;
      }

      // Implement lang.Value
      $body[]= new Method(['public'], 'toString', new Signature([], null), [
        new ReturnStatement(new Code('"'.strtr(substr($node->name, 1), '\\', '.').'('.substr($string, 2).')"'))
      ]);
      $body[]= new Method(['public'], 'hashCode', new Signature([], null), [
        new ReturnStatement(new Code('md5(\\util\\Objects::hashOf(["'.substr($node->name, 1).'"'.$object.']))'))
      ]);
      $body[]= new Method(['public'], 'compareTo', new Signature([new Parameter('value', null)], null), [
        new ReturnStatement(new Code('$value instanceof self
          ? \\util\\Objects::compare(['.substr($object, 2).'], ['.substr($value, 2).'])
          : 1
        '))
      ]);

      return new ClassDeclaration(
        ['final'],
        $node->name,
        null,
        ['\\lang\\Value'],
        $body,
        $node->annotations,
        $node->comment,
        $node->line
      );
    });
  }
}