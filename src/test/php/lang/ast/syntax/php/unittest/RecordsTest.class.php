<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use lang\ast\{Errors, Node};
use lang\reflection\InvocationFailed;
use lang\{Error, Value, Reflection, IllegalArgumentException};
use test\{Assert, Expect, Test, Values};
use util\Objects;

class RecordsTest extends EmittingTest {

  #[Test]
  public function implements_value_interface() {
    $t= $this->declare('record %T(int $x, int $y) { }');
    Assert::equals([Reflection::type(Value::class)], $t->interfaces());
  }

  #[Test]
  public function is_final() {
    $t= $this->declare('record %T(int $x, int $y) { }');
    Assert::equals(MODIFIER_FINAL | MODIFIER_PUBLIC, $t->modifiers()->bits());
  }

  #[Test]
  public function can_extend_base_class() {
    $t= $this->declare('record %T(string $name) extends \\lang\\ast\\Node { }');
    Assert::equals(Reflection::type(Node::class), $t->parent());
  }

  #[Test, Expect(class: Errors::class, message: '/Records cannot have a constructor/')]
  public function cannot_have_constructor() {
    $this->declare('record %T(int $id) {
      public function __construct() { }
    }');
  }

  #[Test]
  public function fields_are_private_by_default() {
    $t= $this->declare('record %T(string $name) { }');
    Assert::equals(MODIFIER_PRIVATE, $t->property('name')->modifiers()->bits());
  }

  #[Test]
  public function can_declare_field_modifiers() {
    $t= $this->declare('record %T(public string $name) { }');
    Assert::equals(MODIFIER_PUBLIC, $t->property('name')->modifiers()->bits());
  }

  #[Test]
  public function can_have_readonly_fields() {
    $t= $this->declare('record %T(public readonly string $name) { }');
    $modifiers= $t->property('name')->modifiers();

    Assert::true($modifiers->isPublic() && $modifiers->isReadonly());
  }

  #[Test, Expect(class: Error::class, message: '/Cannot modify readonly property .+name/')]
  public function writing_to_readonly_field() {
    $t= $this->declare('record %T(public readonly string $name) { }');
    $t->newInstance('Test')->name= 'Modified';
  }

  #[Test]
  public function point_record() {
    $p= $this->declare('record %T(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals([1, 10], [$p->x(), $p->y()]);
  }

  #[Test]
  public function can_declare_further_properties() {
    $p= $this->declare('record %T(string $name) { public int $age= 0; }')->newInstance('Test');
    Assert::equals(0, $p->age);
  }

  #[Test]
  public function can_declare_further_methods() {
    $p= $this->declare('record %T(string $name) { public function age() { return 0; } }')->newInstance('Test');
    Assert::equals(0, $p->age());
  }

  #[Test]
  public function can_implement_interfaces() {
    $t= $this->declare('record %T(int $lo, int $hi) implements \IteratorAggregate {
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
    $p= $this->declare('record %T(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals(nameof($p).'(x: 1, y: 10)', $p->toString());
  }

  #[Test]
  public function user_record_with_overridden_string_representation() {
    $t= $this->declare('record %T(int $id, string $handle) {
      public function toString() {
        return nameof($this)."(#".$this->id.": ".$this->handle.")";
      }
    }');
    Assert::equals($t->name().'(#0: root)', $t->newInstance(0, 'root')->toString());
  }

  #[Test]
  public function hashcode() {
    $p= $this->declare('record %T(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals(md5(Objects::hashOf([get_class($p), 1, 10])), $p->hashCode());
  }

  #[Test]
  public function equality() {
    $t= $this->declare('record %T(int $x, int $y) { }');
    Assert::equals($t->newInstance(1, 10), $t->newInstance(1, 10));
    Assert::notEquals($t->newInstance(1, 10), $t->newInstance(2, 5));
  }

  #[Test, Values([[['Timm', 'Test'], ['Timm', 'Test', null]], [['Timm', 'Test', 'J'], ['Timm', 'Test', 'J']],])]
  public function name_record_with_optional_component($args, $expected) {
    $n= $this->declare('record %T(string $first, string $last, ?string $middle= null) { }')->newInstance(...$args);
    Assert::equals($expected, [$n->first(), $n->last(), $n->middle()]);
  }

  #[Test, Values([[['Timm', 'Test'], 'Timm Test'], [['Timm', 'Test', 'J'], 'Timm J. Test'],])]
  public function name_record_with_method($args, $expected) {
    $t= $this->declare('record %T(string $first, string $last, ?string $middle= null) {
      public function display() {
        return $this->first.(null === $this->middle ? " " : " ".$this->middle.". ").$this->last;
      }
    }');
    Assert::equals($expected, $t->newInstance(...$args)->display());
  }

  #[Test]
  public function can_use_undeclared_varargs() {
    $p= $this->declare('record %T(... $members) { }')->newInstance(1, 2, 3);
    Assert::equals([1, 2, 3], $p->members());
  }

  #[Test]
  public function can_use_declared_varargs() {
    $p= $this->declare('record %T(int... $members) { }')->newInstance(1, 2, 3);
    Assert::equals([1, 2, 3], $p->members());
  }

  #[Test]
  public function can_have_init_block() {
    $t= $this->declare('record %T(int $lo, int $hi) {
      public $initialized= false;

      init {
        $this->initialized= true;
      }
    }');
    Assert::true($t->newInstance(1, 2)->initialized);
  }

  #[Test, Expect(class: IllegalArgumentException::class, message: 'lo > hi!')]
  public function can_verify() {
    $t= $this->declare('record %T(int $lo, int $hi) {
      init {
        if ($this->lo > $this->hi) {
          throw new \\lang\\IllegalArgumentException("lo > hi!");
        }
      }
    }');
    try {
      $t->newInstance(5, 1);
    } catch (InvocationFailed $expected) {
      throw $expected->getCause();
    }
  }

  #[Test]
  public function modifiers_can_be_used() {
    $t= $this->declare('record %T(protected string $name) { }');
    Assert::equals(MODIFIER_PROTECTED, $t->property('name')->modifiers()->bits());
  }

  #[Test]
  public function can_have_initial_values() {
    $t= $this->declare('record %T(array $list= [0]) { }');
    Assert::equals([0], $t->property('list')->get($t->newInstance()));
    Assert::equals([1, 2, 3], $t->property('list')->get($t->newInstance([1, 2, 3])));
  }

  #[Test]
  public function destructure_point_record() {
    $p= $this->declare('record %T(int $x, int $y) { }')->newInstance(1, 10);
    Assert::equals([1, 10], $p());
  }

  #[Test]
  public function destructure_and_map_person_record() {
    $p= $this->declare('record %T(string $name, int $age) { }')->newInstance('Test', 1);
    Assert::equals('Test is 1 year(s) old', $p(function($name, $age) {
      return "{$name} is {$age} year(s) old";
    }));
  }

  #[Test, Expect(class: Error::class, message: '/Argument.+must.+callable/')]
  public function destructure_with_incorrect_mapper() {
    $p= $this->declare('record %T(int $x, int $y) { }')->newInstance(1, 10);
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