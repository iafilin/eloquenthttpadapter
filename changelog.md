# Changelog

All notable changes to `EloquentHttpAdapter` will be documented in this file.

## Version 2.1.0

### Added
- **HttpModelInterface** - Interface for better architecture and testing
- **Custom exceptions** - HttpModelException for better error handling
- **Enhanced error handling** - Configurable error handling with custom exceptions
- **Response validation** - Better validation of API responses
- **Facade support** - HttpModel facade for convenient access
- **Comprehensive tests** - Test suite for HttpModel functionality
- **Enhanced configuration** - More configuration options for customization
- **Better documentation** - Updated examples and usage patterns

### Improved
- **Error handling** - More specific error messages and better exception handling
- **Response validation** - Validation of response format and structure
- **Configuration** - More granular configuration options
- **Testing** - Comprehensive test coverage
- **Architecture** - Interface-based design for better extensibility

### Fixed
- **Method visibility** - Fixed interface compliance issues
- **Error reporting** - Better error reporting and logging
- **Response handling** - Improved handling of invalid responses

## Version 2.0.0

### Breaking Changes
- **Removed InteractsWithHttp trait** - The trait has been completely removed
- **HttpModel is now the only approach** - All models must extend HttpModel
- **No backward compatibility** - Existing code using the trait will break

### Added
- **HttpModel abstract class** - Clean inheritance-based approach
- **Improved error handling** - More robust error management with proper validation
- **Enhanced configuration** - Comprehensive configuration options
- **Better input validation** - Safety checks for ID and response data
- **New methods** - `find()` and `findOrFail()` methods for single record retrieval
- **Configuration file** - Full configuration support with publishable config
- **Better testing support** - Easier to mock and test

### Fixed
- **Critical issue with trait method overriding** - Replaced trait with abstract class
- **Private method accessibility** - Made methods properly accessible for inheritance
- **JSON response validation** - Added checks for response structure
- **Infinite recursion in where parsing** - Added protection against circular references
- **Hardcoded pagination limits** - Made configurable via config file
- **Exception handling** - Improved error handling with proper logging
- **LIKE operator handling** - Fixed wildcard processing for LIKE queries

### Changed
- **Architecture** - Moved from trait-based to inheritance-based approach
- **Method visibility** - Changed private methods to protected for proper inheritance
- **Configuration** - Added comprehensive configuration system
- **Documentation** - Updated README with new usage patterns
- **Version numbering** - Major version bump due to breaking changes

### Removed
- **InteractsWithHttp trait** - Completely removed from the codebase
- **Legacy documentation** - Removed all references to the trait approach
- **Deprecated methods** - All trait-based methods removed

## Version 1.1.0

### Added
- **New HttpModel abstract class** - Replaces trait approach with better architecture
- **Improved error handling** - More robust error management with proper validation
- **Enhanced configuration** - Comprehensive configuration options
- **Better input validation** - Safety checks for ID and response data
- **New methods** - `find()` and `findOrFail()` methods for single record retrieval
- **Configuration file** - Full configuration support with publishable config
- **Backward compatibility** - Trait still works but is deprecated

### Fixed
- **Critical issue with trait method overriding** - Replaced trait with abstract class
- **Private method accessibility** - Made methods properly accessible for inheritance
- **JSON response validation** - Added checks for response structure
- **Infinite recursion in where parsing** - Added protection against circular references
- **Hardcoded pagination limits** - Made configurable via config file
- **Exception handling** - Improved error handling with proper logging
- **LIKE operator handling** - Fixed wildcard processing for LIKE queries

### Changed
- **Architecture** - Moved from trait-based to inheritance-based approach
- **Method visibility** - Changed private methods to protected for proper inheritance
- **Configuration** - Added comprehensive configuration system
- **Documentation** - Updated README with new usage patterns and migration guide

### Deprecated
- **InteractsWithHttp trait** - Use HttpModel instead (still works but deprecated)

## Version 1.0.3

### Added
- Everything
