XP records for PHP
==================

[![Build status on GitHub](https://github.com/xp-lang/xp-records/workflows/Tests/badge.svg)](https://github.com/xp-lang/xp-records/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.4+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_4plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-lang/xp-records/version.svg)](https://packagist.org/packages/xp-lang/xp-records)

Plugin for the [XP Compiler](https://github.com/xp-framework/compiler/) which adds a `record` syntax to the PHP language. Records declare a final class with immutable components for each of its members and appropriate accessors and a constructor, which implements the `lang.Value` interface.

Example
-------

```php
// Declaration
namespace com\example;

use IteratorAggregate, Traversable;

record Range(int $lo, int $hi) implements IteratorAggregate {
  public function getIterator(): Traversable {
    for ($i= $this->lo; $i <= $this->hi; $i++) {
      yield $i;
    }
  }
}

// Usage
$r= new Range(1, 10);
$r->lo();       // 1
$r->hi();       // 10
$r->toString(); // "com.example.Range(lo: 1, hi: 10)"

foreach ($r as $item) {
  // 1, 2, 3, ... 10
}
```

*Note: The generated `toString()`, `hashCode()` and `compareTo()` methods may be overriden by supplying an implementation in the record body.*

Initialization
--------------
To verify constructor parameters, add an initialization block as follows:

```php
use lang\IllegalArgumentException;

record Range(int $lo, int $hi) {
  init {
    if ($this->lo > $this->hi) {
      throw new IllegalArgumentException('Lower border may not exceed upper border');
    }
  }
}
```

This block is called *after* the members have been initialized from the constructor parameters.

Destructuring
-------------
To destructure a record into its members, use the invocation syntax:

```php
// Using the declaration from above:
$r= new Range(1, 10);

// Use https://wiki.php.net/rfc/short_list_syntax (>= PHP 7.1)
[$lo, $hi]= $r();

// Optionally map the members, returns the string "1..10"
$string= $r(fn($lo, $hi) => "{$lo}..{$hi}");
```

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
* [Java 14 Records](https://docs.oracle.com/en/java/javase/14/language/records.html)
* [Java 14 Feature Spotlight: Records](https://www.infoq.com/articles/java-14-feature-spotlight/)
* [C# structs](https://docs.microsoft.com/en-us/dotnet/csharp/language-reference/builtin-types/struct)
* [C# records](https://docs.microsoft.com/en-us/dotnet/csharp/language-reference/builtin-types/record)