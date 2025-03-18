<?php
/**
 * Plugin Name: Ultimate Member Text Domain Debug
 * Description: A debugging plugin to track when and where the Ultimate Member text domain is being loaded
 * Version: 1.0.0
 * Author: Debug Helper
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Track the current WordPress action being executed
 */
global $wp_actions;
$wp_actions = array();

// Add early hooks to catch theme initialization
add_action('after_setup_theme', function() {
    error_log(sprintf(
        "[%s] After Setup Theme Hook:\n" .
        "Current Action: %s\n" .
        "Is Before Init: %s\n" .
        "Loaded Text Domains: %s\n" .
        "Backtrace:\n%s",
        current_time('mysql'),
        current_action(),
        did_action('init') ? 'No' : 'Yes',
        implode(', ', get_loaded_textdomains()),
        wp_debug_backtrace_summary()
    ));
}, -999999);

/**
 * Get file content around a specific line
 */
function um_get_file_context($file, $line, $context_lines = 5) {
    if (!file_exists($file)) {
        return "File not found: $file";
    }
    
    $lines = file($file);
    if ($lines === false) {
        return "Could not read file: $file";
    }
    
    $start = max(0, $line - $context_lines - 1);
    $end = min(count($lines), $line + $context_lines);
    $context = array();
    
    for ($i = $start; $i < $end; $i++) {
        $prefix = ($i + 1 === $line) ? '> ' : '  ';
        $context[] = $prefix . ($i + 1) . ': ' . rtrim($lines[$i]);
    }
    
    return implode("\n", $context);
}

// Track avia_superobject initialization sequence
add_action('after_setup_theme', function() {
    if (class_exists('avia_superobject')) {
        $reflection = new ReflectionClass('avia_superobject');
        $file = $reflection->getFileName();
        
        error_log(sprintf(
            "[%s] Avia Initialization Sequence:\n" .
            "File: %s\n" .
            "Hook: %s\n" .
            "Is Before Init: %s\n" .
            "Loaded Text Domains: %s\n" .
            "Stack:\n%s",
            current_time('mysql'),
            $file,
            current_action(),
            did_action('init') ? 'No' : 'Yes',
            implode(', ', get_loaded_textdomains()),
            wp_debug_backtrace_summary()
        ));
    }
}, -999999);

add_action('init', function() {
    error_log(sprintf(
        "[%s] Init Hook:\n" .
        "Current Action: %s\n" .
        "Loaded Text Domains: %s\n" .
        "Backtrace:\n%s",
        current_time('mysql'),
        current_action(),
        implode(', ', get_loaded_textdomains()),
        wp_debug_backtrace_summary()
    ));
}, -999999);

// Add specific tracking for Enfold theme setup
add_action('avia_backend_theme_activation', function() {
    error_log(sprintf(
        "[%s] Enfold Theme Activation:\n" .
        "Current Action: %s\n" .
        "Is Before Init: %s\n" .
        "Backtrace:\n%s",
        current_time('mysql'),
        current_action(),
        did_action('init') ? 'No' : 'Yes',
        wp_debug_backtrace_summary()
    ));
}, -999999);

/**
 * Helper function to get loaded text domains
 */
function get_loaded_textdomains() {
    global $l10n;
    return is_array($l10n) ? array_keys($l10n) : array();
}

/**
 * Track translation function calls
 */
function um_track_translation_calls($text, $domain = '') {
    if ($domain === 'ultimate-member') {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $caller = array();
        $theme_calls = array();
        $full_trace = array();
        $enfold_files = array();
        $avia_super_calls = array();
        
        // First, collect the full trace for context
        foreach ($backtrace as $trace) {
            if (isset($trace['file'])) {
                $full_trace[] = sprintf(
                    "File: %s\nLine: %d\nFunction: %s\nClass: %s",
                    $trace['file'],
                    $trace['line'],
                    $trace['function'],
                    isset($trace['class']) ? $trace['class'] : 'N/A'
                );

                // Specifically track Enfold files and avia_superobject
                if (strpos($trace['file'], 'wp-content/themes/enfold') !== false) {
                    $enfold_files[] = $trace['file'];
                    
                    // Check for avia_superobject
                    if (isset($trace['class']) && $trace['class'] === 'avia_superobject') {
                        $file_context = um_get_file_context($trace['file'], $trace['line']);
                        $avia_super_calls[] = sprintf(
                            "File: %s\nLine: %d\nMethod: %s\nContext:\n%s\n",
                            $trace['file'],
                            $trace['line'],
                            $trace['function'],
                            $file_context
                        );
                    }
                }
            }
        }

        if (!empty($theme_calls) || !empty($avia_super_calls)) {
            error_log(sprintf(
                "[%s] Ultimate Member Translation Call:\n" .
                "Text Being Translated: '%s'\n" .
                "Domain: '%s'\n" .
                "Current Hook: %s\n" .
                "Current Action: %s\n" .
                "Is Before Init: %s\n" .
                "Current Filter: %s\n" .
                "Loaded Text Domains: %s\n" .
                "Avia Superobject Calls:\n%s\n" .
                "Enfold Files Involved:\n%s\n" .
                "Theme Calls:\n%s\n" .
                "Full Trace:\n%s\n",
                current_time('mysql'),
                $text,
                $domain,
                current_filter(),
                current_action(),
                did_action('init') ? 'No' : 'Yes',
                current_filter(),
                implode(', ', get_loaded_textdomains()),
                !empty($avia_super_calls) ? implode("\n", $avia_super_calls) : "No direct avia_superobject calls",
                implode("\n", array_unique($enfold_files)),
                implode("\n", $theme_calls),
                implode("\n", $full_trace)
            ));
        }
    }
    return $text;
}

// Add early priority to catch translations as soon as possible
add_filter('gettext', 'um_track_translation_calls', -999999, 2);
add_filter('gettext_with_context', 'um_track_translation_calls', -999999, 2);
add_filter('ngettext', 'um_track_translation_calls', -999999, 2);
add_filter('ngettext_with_context', 'um_track_translation_calls', -999999, 2);

/**
 * Track theme loading
 */
function um_track_theme_loading($theme) {
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
    $theme_calls = array();
    
    foreach ($backtrace as $trace) {
        if (isset($trace['file']) && strpos($trace['file'], 'wp-content/themes/enfold') !== false) {
            $file_context = um_get_file_context($trace['file'], $trace['line']);
            $theme_calls[] = sprintf(
                "File: %s\nLine: %d\nFunction: %s\nClass: %s\nContext:\n%s\n",
                $trace['file'],
                $trace['line'],
                $trace['function'],
                isset($trace['class']) ? $trace['class'] : 'N/A',
                $file_context
            );
        }
    }

    error_log(sprintf(
        "[%s] Theme Loading:\nTheme: %s\nCurrent Action: %s\nCurrent Hook: %s\nIs Before Init: %s\nLoaded Text Domains: %s\nTheme Calls:\n%s\n",
        current_time('mysql'),
        $theme,
        current_action(),
        current_filter(),
        did_action('init') ? 'No' : 'Yes',
        implode(', ', get_loaded_textdomains()),
        !empty($theme_calls) ? implode("\n", $theme_calls) : "No theme calls found"
    ));
    return $theme;
}
add_filter('template', 'um_track_theme_loading', -999999);

/**
 * Track Ultimate Member text domain loading
 */
function um_textdomain_debug_track($load_textdomain, $domain) {
    if ($domain !== 'ultimate-member') {
        return $load_textdomain;
    }

    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
    $avia_calls = array();
    
    foreach ($backtrace as $trace) {
        if (isset($trace['file']) && strpos($trace['file'], 'wp-content/themes/enfold') !== false) {
            $file_context = um_get_file_context($trace['file'], $trace['line']);
            $avia_calls[] = sprintf(
                "File: %s\nLine: %d\nFunction: %s\nContext:\n%s",
                $trace['file'],
                $trace['line'],
                $trace['function'],
                $file_context
            );
        }
    }

    if (!empty($avia_calls)) {
        error_log(sprintf(
            "[%s] Ultimate Member Text Domain Loading:\n" .
            "Hook: %s\n" .
            "Is Before Init: %s\n" .
            "Avia Calls:\n%s",
            current_time('mysql'),
            current_action(),
            did_action('init') ? 'No' : 'Yes',
            implode("\n\n", $avia_calls)
        ));
    }

    return $load_textdomain;
}
add_filter('_load_textdomain_just_in_time', 'um_textdomain_debug_track', -999999, 2);

/**
 * Track all WordPress actions
 */
add_action('all', function() {
    global $wp_actions;
    $wp_actions[] = current_filter();
}); 