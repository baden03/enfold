# Ultimate Member Text Domain Loading Issue with Enfold Theme

## Overview
This repository documents an issue where the Enfold theme triggers premature loading of the Ultimate Member plugin's text domain. The issue occurs during theme initialization, specifically when `avia_superobject::instance()` is called during the `after_setup_theme` hook, before WordPress's `init` hook has fired.

## Issue Details

### The Problem
The Enfold theme's core initialization process triggers WordPress translation functions before the recommended hook (`init`), causing WordPress to generate "doing it wrong" notices in debug logs.

### Technical Sequence
1. WordPress loads the Enfold theme
2. During `after_setup_theme` hook:
   - Enfold initializes its core object (`avia_superobject`)
   - This triggers translation function calls
   - WordPress attempts to load Ultimate Member's text domain
3. The text domain loading occurs before the `init` hook
4. WordPress generates a notice about early text domain loading

### Debug Output Example
```php
[timestamp] Ultimate Member Translation Call:
Text Being Translated: '[text]'
Domain: 'ultimate-member'
Current Hook: after_setup_theme
Is Before Init: Yes
Stack Trace: 
  - avia_superobject::instance() 
  - [stack trace continues...]
```

## Impact

### Direct Effects
- PHP Notices in debug log
- Text domain loaded earlier than WordPress recommends
- Debug log pollution

### Indirect Effects
- Potential translation inconsistencies
- Non-compliance with WordPress best practices
- Possible issues with translation plugins or multilingual setups

## Root Cause Analysis

### Enfold Theme
The issue originates in the Enfold theme's initialization process:
```php
// In Enfold theme initialization
return avia_superobject::instance($base_data);
```
This is called during `after_setup_theme`, triggering translation functions too early.

### WordPress Translation Loading
WordPress expects text domains to be loaded during or after the `init` hook, as documented in the WordPress core:
```php
_doing_it_wrong( __FUNCTION__, __( 'Text domain loading should be performed during init.' ), '6.3.0' );
```

## Recommended Solutions

### For Enfold Theme Developers
```php
// Current implementation (problematic)
return avia_superobject::instance($base_data);

// Recommended implementation
add_action('init', function() use ($base_data) {
    return avia_superobject::instance($base_data);
}, 1);
```

### For Ultimate Member Developers
Consider implementing one of these solutions:

1. Compatibility Layer
```php
add_action('after_setup_theme', function() {
    if (defined('AVIA_FW')) {
        // Special handling for Enfold theme
        add_filter('ultimate_member_text_domain_loading', '__return_true');
    }
}, 0);
```

2. Conditional Loading
```php
function um_load_text_domain() {
    if (did_action('init') || current_filter() === 'init') {
        // Load text domain normally
    } else {
        // Defer loading or handle early loading case
    }
}
```

### Temporary Workaround
Create a must-use plugin with this code:
```php
<?php
/*
Plugin Name: Ultimate Member Text Domain Loading Fix
Description: Fixes text domain loading issue with Enfold theme
Version: 1.0
*/

add_action('plugins_loaded', function() {
    if (defined('AVIA_FW')) {
        remove_action('plugins_loaded', array('Ultimate_Member', 'load_textdomain'));
        add_action('init', array('Ultimate_Member', 'load_textdomain'), 0);
    }
}, 0);
```

## Testing and Verification

### Test Environment
- WordPress 6.3+
- Debug mode enabled
- Ultimate Member plugin
- Enfold theme

### Reproduction Steps
1. Install and activate Ultimate Member plugin
2. Install and activate Enfold theme
3. Enable WordPress debug mode
4. Load any page
5. Check debug.log for notices

### Expected Behavior After Fix
- No "doing it wrong" notices in debug log
- Text domain loads during `init` hook
- All translations work correctly

## Additional Resources

### Related Documentation
- [WordPress Text Domain Loading](https://developer.wordpress.org/reference/functions/load_textdomain/)
- [WordPress Hook Sequence](https://developer.wordpress.org/plugins/hooks/actions/)
- [Translation Best Practices](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)

### Debug Plugin
This repository includes a debug plugin (`um-textdomain-debug.php`) that can be used to track and analyze the text domain loading sequence.

## Contributing
If you have additional insights or alternative solutions, please:
1. Fork this repository
2. Create a feature branch
3. Submit a Pull Request

## License
This documentation and associated code are provided under the MIT License.

## Support
- For Enfold theme issues: [Kriesi Support](https://kriesi.at/support)
- For Ultimate Member issues: [Ultimate Member Support](https://ultimatemember.com/support/)