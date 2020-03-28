XP records for PHP
==================

[![Build Status on TravisCI](https://secure.travis-ci.org/xp-lang/xp-records.svg)](http://travis-ci.org/xp-lang/xp-records)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.png)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-lang/xp-records/version.png)](https://packagist.org/packages/xp-lang/xp-records)

Plugin for the [XP Compiler](https://github.com/xp-framework/compiler/) which adds a `record` syntax to the PHP language. Records declare a final class with immutable components for each of its members and appropriate accessors and a constructor, which implements the `lang.Value` interface.

Example
-------

```php
// Declaration
namespace com\example;

record Range(int $lo, int $hi) {
  public function distance(): int {
    return $this->hi - $this->lo;
  }
}

// Usage
$r= new Range(1, 10);
$r->lo();       // 1
$r->hi();       // 10
$r->distance(); // 9
$r->toString(); // "com.example.Range(lo= 1, hi= 10)"
```

*Note: The generated `toString()`, `hashCode()` and `compareTo()` methods may be overriden by supplying an implementation in the record body.*

Installation
------------
After installing the XP Compiler into your project, also include this plugin.

```bash
$ composer require xp-framework/compiler
# ...

$ composer require xp-lang/xp-records
# ...
```

No further action is required.

See also
--------
* [Kotlin Data Classes](https://kotlinlang.org/docs/reference/data-classes.html)
* [Java 14 Feature Spotlight: Records](https://www.infoq.com/articles/java-14-feature-spotlight/)
* [C# structs](https://docs.microsoft.com/en-us/dotnet/csharp/language-reference/builtin-types/struct)
* [C# records proposal](https://github.com/dotnet/csharplang/blob/master/proposals/records.md)