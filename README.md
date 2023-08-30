## Ardillo ReactPHP Event Loop

[![CI Status](https://github.com/ardillo-php/loop/workflows/CI/badge.svg)](https://github.com/ardillo-php/loop/actions/workflows/ci.yaml)
[![Psalm Type Coverage](https://shepherd.dev/github/ardillo-php/loop/coverage.svg)](https://shepherd.dev/github/ardillo-php/loop)
[![Latest Stable Version](https://poser.pugx.org/ardillo/loop/v/stable.png)](https://packagist.org/packages/ardillo/loop)
[![Installs on Packagist](https://img.shields.io/packagist/dt/ardillo/loop?color=blue&label=Installs%20on%20Packagist)](https://packagist.org/packages/ardillo/loop)
[![Test Coverage](https://api.codeclimate.com/v1/badges/e53a5afe5367ce230546/test_coverage)](https://codeclimate.com/github/ardillo-php/loop/test_coverage)
[![Maintainability](https://api.codeclimate.com/v1/badges/e53a5afe5367ce230546/maintainability)](https://codeclimate.com/github/ardillo-php/loop/maintainability)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Ardillo Loop is an implementation of the ReactPHP Event Loop [interface](https://github.com/reactphp/event-loop).

For documentation, please refer to the ReactPHP loop API reference: https://reactphp.org/event-loop/ as well as our examples: https://github.com/ardillo-php/examples

_Note:_ Unlike the ReactPHP loop, Ardillo Loop cannot be restarted, i.e. once stopped, the implementing application is expected to prepare for termination. Given the nature of Ardillo applications (native desktop utilities), this should no be a limitation, however this can affect one's approach to unit testing.

This library also offers a [`ReactApp` class](src/ReactApp.php) which extends the base [Ardillo `App` class](https://ardillo.dev/docs/0.1.x/classes/app/) in such fashion that the actual event loop management is abstracted away from the application logic. This allows for a more natural approach to writing Ardillo applications, where the application logic is not bound to the event loop implementation.

### Installation

Before proceeding, make sure you have installed and enabled the [Ardillo extension](https://github.com/ardillo-php/ext).

The recommended way to install Ardillo Loop is [via Composer](https://getcomposer.org/):

```sh
composer require ardillo/loop
```

### Tests and Static Analysis

In order to run the tests, you need to clone the repository and install the dependencies via Composer:

```sh
git clone https://github.com/ardillo-php/loop.git
cd loop
composer install
```

You can then run the tests using the following command:

```sh
composer test
```

To run static analysis (PHPStan and Psalm), use the following commands:

```sh
composer phpstan
composer psalm
```

### License

The MIT License (MIT). Please see [`LICENSE`](LICENSE) for more information.

### Acknowledgments

This library extends the [ReactPHP Event Loop](https://github.com/reactphp/event-loop) and is inspired by the [libuv loop](https://github.com/reactphp/event-loop/blob/1.x/src/ExtUvLoop.php) implementation.

### Contributing

Bug reports (and small patches) can be submitted via the [issue tracker](https://github.com/ardillo-php/loop/issues). Forking the repository and submitting a Pull Request is preferred for substantial patches. Please be sure to read and comply with the [Contributing Terms](CONTRIBUTING.md) document before proceeding.
