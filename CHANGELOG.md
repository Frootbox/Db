# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0] - 2023-07-07

### Changed

- Changed constructor of main class.

## [0.2.1] - 2023-06-09

### Added

- Added generic bool getter Row::isXXX()
- Added PHPDoc annotations

### Changed

- Renamed CHANGELOG to CHANGELOG.md
- Changed php minimum version to 8.0
- Change model::insert() to model::persist()

## [0.2.0] - 2023-01-28

## [0.1.1] - 2022-18-10

## [0.1.0] - 2022-09-26

## [0.0.9] - 2021-10-04

## [0.0.8] - 2020-10-29

## [0.0.7] - 2020-03-02

## [0.0.6] - 2019-09-02

### Added

- Method Db::getSchema()
- Method Result::getTotal()
- Experimental export Classes
- Method Rows\NestedSet::getSiblings()
- Method Rows\NestedSet::isChildOf()
- Method Rows\NestedSet::getOffspring()

### Fixed

- A bug when setting attributes to null

## [0.0.5] - 2019-08-31

### Added

- Condition MatchColumn
- Method Row::getDataRaw()
- Support for {var} syntax
- Method Rows\NestedSet::getParent()
- Method Row::unset()
- License information to composer file
- Method Result::reverse()
- Feature InsertDefaults

### Fixed

- An error where NULL values can't be updated
- Model::insert() correctly sets columns to NULL if value is NULL

## [0.0.4] - 2019-07-09 

### Added

- Support for dynamic model classes per row
- License information

## [0.0.3] - 2019-07-01

### Added

- Changelog