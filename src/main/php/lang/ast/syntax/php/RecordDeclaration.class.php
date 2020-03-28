<?php namespace lang\ast\syntax\php;

use lang\ast\nodes\TypeDeclaration;

class RecordDeclaration extends TypeDeclaration {
  public $kind= 'record';
  public $name, $modifiers, $components, $implements, $body, $annotations, $comment;

  public function __construct($modifiers, $name, $components, $implements, $body, $annotations= [], $comment= null, $line= -1) {
    $this->modifiers= $modifiers;
    $this->name= $name;
    $this->components= $components;
    $this->implements= $implements;
    $this->body= $body;
    $this->annotations= $annotations;
    $this->comment= $comment;
    $this->line= $line;
  }
}