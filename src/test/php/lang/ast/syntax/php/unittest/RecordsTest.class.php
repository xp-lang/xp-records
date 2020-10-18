<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\Errors;
use lang\ast\unittest\emit\EmittingTest;
use lang\{IllegalArgumentException, XPClass};
use unittest\{Assert, Expect, Test, Values};
use util\Objects;

class RecordsTest extends EmittingTest {

  #[Test]
  public function implements_value_interface() {
    $t= $this->type('record <T>(int $x, int $y) { }');
    Assert::equals([XPClass::forName('lang.Value')], $t->getInterfaces());
  }

  #[Test]
  public function is_final() {
    $t= $this->type('record <T>(int $x, int $y) { }');
    Assert::equals(MODIFIER_FINAL | MODIFIER_PUBLIC, $t->getModifiers());
  }

  #[Test]
  public function can_extend_base_class() {
    $t= $this->type('record <T>(string $name) extends \\lang\\ast\\Node { }');
    Assert::equals(XPClass::forName('lang.ast.Node'), $t->getParentClass());
  }

  #[Test, Expect(['class' => Errors::class, 'withMessage' => '/Records cannot have a constructor/'])]
  public function cannot_have_constructor() {
    $this->type('record <T>(int $id) {
      public function __construct() { }
    }');
  }

  #[Test]
  public function point_record() {
    $p= $this->type('record <T>(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals([1, 10], [$p->x(), $p->y()]);
  }

  #[Test]
  public function can_declare_further_properties() {
    $p= $this->type('record <T>(string $name) { public int $age= 0; }')->newInstance('Test');
    Assert::equals(0, $p->age);
  }

  #[Test]
  public function can_declare_further_methods() {
    $p= $this->type('record <T>(string $name) { public function age() { return 0; } }')->newInstance('Test');
    Assert::equals(0, $p->age());
  }

  #[Test]
  public function can_implement_interfaces() {
    $t= $this->type('record <T>(int $lo, int $hi) implements \IteratorAggregate {
      public function getIterator() {
        for ($i= $this->lo; $i <= $this->hi; $i++) {
          yield $i;
        }
      }
    }');
    Assert::equals([1, 2, 3, 4, 5], iterator_to_array($t->newInstance(1, 5)));
  }

  #[Test]
  public function string_representation() {
    $p= $this->type('record <T>(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals(nameof($p).'(x= 1, y= 10)', $p->toString());
  }

  #[Test]
  public function user_record_with_overridden_string_representation() {
    $t= $this->type('record <T>(int $id, string $handle) {
      public function toString() {
        return nameof($this)."(#".$this->id.": ".$this->handle.")";
      }
    }');
    Assert::equals($t->getName().'(#0: root)', $t->newInstance(0, 'root')->toString());
  }

  #[Test]
  public function hashcode() {
    $p= $this->type('record <T>(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals(md5(Objects::hashOf([get_class($p), 1, 10])), $p->hashCode());
  }

  #[Test]
  public function equality() {
    $t= $this->type('record <T>(int $x, int $y) { }');
    Assert::equals($t->newInstance(1, 10), $t->newInstance(1, 10));
    Assert::notEquals($t->newInstance(1, 10), $t->newInstance(2, 5));
  }

  #[Test, Values([[['Timm', 'Test'], ['Timm', 'Test', null]], [['Timm', 'Test', 'J'], ['Timm', 'Test', 'J']],])]
  public function name_record_with_optional_component($args, $expected) {
    $n= $this->type('record <T>(string $first, string $last, ?string $middle= null) { }')->newInstance(...$args);
    Assert::equals($expected, [$n->first(), $n->last(), $n->middle()]);
  }

  #[Test, Values([[['Timm', 'Test'], 'Timm Test'], [['Timm', 'Test', 'J'], 'Timm J. Test'],])]
  public function name_record_with_method($args, $expected) {
    $t= $this->type('record <T>(string $first, string $last, ?string $middle= null) {
      public function display() {
        return $this->first.(null === $this->middle ? " " : " ".$this->middle.". ").$this->last;
      }
    }');
    Assert::equals($expected, $t->newInstance(...$args)->display());
  }

  #[Test]
  public function can_use_untyped_varargs() {
    $p= $this->type('record <T>(... $members) { }')->newInstance(1, 2, 3);
    Assert::equals([1, 2, 3], $p->members());
  }

  #[Test]
  public function can_use_typed_varargs() {
    $p= $this->type('record <T>(int... $members) { }')->newInstance(1, 2, 3);
    Assert::equals([1, 2, 3], $p->members());
  }

  #[Test, Expect(['class' => IllegalArgumentException::class, 'withMessage' => 'lo > hi!'])]
  public function can_verify() {
    $t= $this->type('record <T>(int $lo, int $hi) {
      public function __init() {
        if ($this->lo > $this->hi) {
          throw new \\lang\\IllegalArgumentException("lo > hi!");
        }
      }
    }');
    $t->newInstance(5, 1);
  }

  #[Test]
  public function modifiers_can_be_used() {
    $t= $this->type('record <T>(protected string $name) { }');
    Assert::equals(MODIFIER_PROTECTED, $t->getField('name')->getModifiers());
  }
}