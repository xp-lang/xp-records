XP records for PHP - ChangeLog
==============================

## ?.?.? / ????-??-??

* Removed deprecated `__init()` function in favor of `init { }`
  blocks see PR #8 and 

## 2.4.0 / 2022-12-04

* Adapt to AST type refactoring (xp-framework/ast#39) - @thekid

## 2.3.0 / 2022-02-05

* Add support for `readonly` record parameters. See issue #7 - @thekid

## 2.2.0 / 2022-02-03

* Merged PR #8: Replace `__init()` function with `init { }` blocks
  (@thekid)

## 2.1.0 / 2022-02-03

* Implemented feature suggested in #6: Add destructuring ability to
  records which will return and optionally map its members. Inspired
  by https://benjiweber.co.uk/blog/2020/09/19/fun-with-java-records/
  (@thekid)

## 2.0.0 / 2022-01-24

* Made compatible with compiler version 8.0.0, and dropped support
  for versions older than 7.0.0.
  (@thekid)
* Changed string representation to resemble PHP 8 named arguments
  (@thekid)

## 1.2.1 / 2021-10-21

* Made compatible with XP 11, Compiler version 7.0.0 - @thekid

## 1.2.0 / 2020-11-28

* Made compatible with XP Compiler version 6.0.0 - @thekid

## 1.1.1 / 2020-11-15

* Refactored to use more specific `lang.ast.types.IsLiteral` instead of
  the base class `lang.ast.Type`
  (@thekid)

## 1.1.0 / 2020-10-18

* Merged PR #4: Add ability to use visibility modifiers - @thekid

## 1.0.2 / 2020-10-01

* Fixed "Undefined property: lang\ast\Scope::$annotations" warnings
  (@thekid)

## 1.0.1 / 2020-05-10

* Merged PR #3: Pass enclosing type to typeBody(), adjusting this
  library in a forward compatible manner w/ upcoming compiler changes
  (@thekid)

## 1.0.0 / 2020-03-28

* Fixed issue #2: Add logic to constructor - @thekid

## 0.4.0 / 2020-03-28

* Fixed variadic types yielding incorrect accessors - @thekid
* Added ability for records to extend base classes - @thekid
* Added ability for records to implement interfaces - @thekid

## 0.3.0 / 2020-03-28

* Fixed issue #1: Allow overriding default `lang.Value` method implementations
  (@thekid)
* Added return types to `toString()`, `hashCode()` and `compareTo()` methods
  (@thekid)

## 0.2.0 / 2020-03-28

* Used dotted class name inside `toString()`- @thekid
* Fixed declared type not being `final` - @thekid

## 0.1.0 / 2020-03-28

* Hello World! First release - @thekid