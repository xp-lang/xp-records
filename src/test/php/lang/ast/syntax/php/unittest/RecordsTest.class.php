<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\Errors;
use lang\ast\unittest\emit\EmittingTest;
use lang\{Error, Value};
use lang\{IllegalArgumentException, XPClass};
use test\{Assert, Expect, Test, Values};
use util\Objects;

class RecordsTest extends EmittingTest {

  #[Test]
  public function implements_value_interface() {
    $t= $this->type('record %T(int $x, int $y) { }');
    Assert::equals([XPClass::forName('lang.Value')], $t->getInterfaces());
  }

  #[Test]
  public function is_final() {
    $t= $this->type('record %T(int $x, int $y) { }');
    Assert::equals(MODIFIER_FINAL | MODIFIER_PUBLIC, $t->getModifiers());
  }

  #[Test]
  public function can_extend_base_class() {
    $t= $this->type('record %T(string $name) extends \\lang\\ast\\Node { }');
    Assert::equals(XPClass::forName('lang.ast.Node'), $t->getParentClass());
  }

  #[Test, Expect(class: Errors::class, message: '/Records cannot have a constructor/')]
  public function cannot_have_constructor() {
    $this->type('record %T(int $id) {
      public function __construct() { }
    }');
  }

  #[Test]
  public function fields_are_private_by_default() {
    $t= $this->type('record %T(string $name) { }');
    Assert::equals(MODIFIER_PRIVATE, $t->getField('name')->getModifiers());
  }

  #[Test]
  public function can_declare_field_modifiers() {
    $t= $this->type('record %T(public string $name) { }');
    Assert::equals(MODIFIER_PUBLIC, $t->getField('name')->getModifiers());
  }

  #[Test]
  public function can_have_readonly_fields() {
    $t= $this->type('record %T(public readonly string $name) { }');
    Assert::equals(MODIFIER_PUBLIC | MODIFIER_READONLY, $t->getField('name')->getModifiers());
  }

  #[Test, Expect(class: Error::class, message: '/Cannot modify readonly property .+name/')]
  public function writing_to_readonly_field() {
    $t= $this->type('record %T(public readonly string $name) { }');
    $t->newInstance('Test')->name= 'Modified';
  }

  #[Test]
  public function point_record() {
    $p= $this->type('record %T(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals([1, 10], [$p->x(), $p->y()]);
  }

  #[Test]
  public function can_declare_further_properties() {
    $p= $this->type('record %T(string $name) { public int $age= 0; }')->newInstance('Test');
    Assert::equals(0, $p->age);
  }

  #[Test]
  public function can_declare_further_methods() {
    $p= $this->type('record %T(string $name) { public function age() { return 0; } }')->newInstance('Test');
    Assert::equals(0, $p->age());
  }

  #[Test]
  public function can_implement_interfaces() {
    $t= $this->type('record %T(int $lo, int $hi) implements \IteratorAggregate {
      public function getIterator(): \Traversable {
        for ($i= $this->lo; $i <= $this->hi; $i++) {
          yield $i;
        }
      }
    }');
    Assert::equals([1, 2, 3, 4, 5], iterator_to_array($t->newInstance(1, 5)));
  }

  #[Test]
  public function string_representation() {
    $p= $this->type('record %T(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals(nameof($p).'(x: 1, y: 10)', $p->toString());
  }

  #[Test]
  public function user_record_with_overridden_string_representation() {
    $t= $this->type('record %T(int $id, string $handle) {
      public function toString() {
        return nameof($this)."(#".$this->id.": ".$this->handle.")";
      }
    }');
    Assert::equals($t->getName().'(#0: root)', $t->newInstance(0, 'root')->toString());
  }

  #[Test]
  public function hashcode() {
    $p= $this->type('record %T(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals(md5(Objects::hashOf([get_class($p), 1, 10])), $p->hashCode());
  }

  #[Test]
  public function equality() {
    $t= $this->type('record %T(int $x, int $y) { }');
    Assert::equals($t->newInstance(1, 10), $t->newInstance(1, 10));
    Assert::notEquals($t->newInstance(1, 10), $t->newInstance(2, 5));
  }

  #[Test, Values([[['Timm', 'Test'], ['Timm', 'Test', null]], [['Timm', 'Test', 'J'], ['Timm', 'Test', 'J']],])]
  public function name_record_with_optional_component($args, $expected) {
    $n= $this->type('record %T(string $first, string $last, ?string $middle= null) { }')->newInstance(...$args);
    Assert::equals($expected, [$n->first(), $n->last(), $n->middle()]);
  }

  #[Test, Values([[['Timm', 'Test'], 'Timm Test'], [['Timm', 'Test', 'J'], 'Timm J. Test'],])]
  public function name_record_with_method($args, $expected) {
    $t= $this->type('record %T(string $first, string $last, ?string $middle= null) {
      public function display() {
        return $this->first.(null === $this->middle ? " " : " ".$this->middle.". ").$this->last;
      }
    }');
    Assert::equals($expected, $t->newInstance(...$args)->display());
  }

  #[Test]
  public function can_use_untyped_varargs() {
    $p= $this->type('record %T(... $members) { }')->newInstance(1, 2, 3);
    Assert::equals([1, 2, 3], $p->members());
  }

  #[Test]
  public function can_use_typed_varargs() {
    $p= $this->type('record %T(int... $members) { }')->newInstance(1, 2, 3);
    Assert::equals([1, 2, 3], $p->members());
  }

  #[Test]
  public function can_have_init_block() {
    $t= $this->type('record %T(int $lo, int $hi) {
      public $initialized= false;

      init {
        $this->initialized= true;
      }
    }');
    Assert::true($t->newInstance(1, 2)->initialized);
  }

  #[Test, Expect(class: IllegalArgumentException::class, message: 'lo > hi!')]
  public function can_verify() {
    $t= $this->type('record %T(int $lo, int $hi) {
      init {
        if ($this->lo > $this->hi) {
          throw new \\lang\\IllegalArgumentException("lo > hi!");
        }
      }
    }');
    $t->newInstance(5, 1);
  }

  #[Test]
  public function modifiers_can_be_used() {
    $t= $this->type('record %T(protected string $name) { }');
    Assert::equals(MODIFIER_PROTECTED, $t->getField('name')->getModifiers());
  }

  #[Test]
  public function can_have_initial_values() {
    $t= $this->type('record %T(array $list= [0]) { }');
    Assert::equals([0], $t->getField('list')->setAccessible(true)->get($t->newInstance()));
    Assert::equals([1, 2, 3], $t->getField('list')->setAccessible(true)->get($t->newInstance([1, 2, 3])));
  }

  #[Test]
  public function destructure_point_record() {
    $p= $this->type('record %T(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals([1, 10], $p());
  }

  #[Test]
  public function destructure_and_map_person_record() {
    $p= $this->type('record %T(string $name, int $age) { }')->newInstance('Test', 1);
    Assert::equals('Test is 1 year(s) old', $p(function($name, $age) {
      return "{$name} is {$age} year(s) old";
    }));
  }

  #[Test, Expect(class: Error::class, message: '/Argument.+must.+callable/')]
  public function destructure_with_incorrect_mapper() {
    $p= $this->type('record %T(int $x, int $y) { }')->newInstance(1, 10);
    $p('not.callable');
  }

  #[Test]
  public function anonymous_record() {
    $p= $this->run('class %T {
      public function run() {
        return new record(name: "Timm", age: 44) { };
      }
    }');
    Assert::equals('record(name: "Timm", age: 44)', $p->toString());
    Assert::instance(Value::class, $p);
  }
}