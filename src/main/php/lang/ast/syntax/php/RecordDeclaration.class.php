<?php namespace lang\ast\syntax\php;

use lang\ast\nodes\TypeDeclaration;

class RecordDeclaration extends TypeDeclaration {
  public $kind= 'record';
  public $name, $modifiers, $parent, $implements, $components, $body, $annotations, $comment;

  public function __construct($modifiers, $name, $components, $body, $annotations= [], $comment= null, $line= -1) {
    $this->modifiers= $modifiers;
    $this->name= $name;
    $this->components= $components;
    $this->body= $body;
    $this->annotations= $annotations;
    $this->comment= $comment;
    $this->line= $line;
  }
}