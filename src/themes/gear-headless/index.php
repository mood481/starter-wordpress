<?php
/**
 * WordPress requires a valid theme; the public site is served elsewhere.
 */
defined('ABSPATH') || exit;
status_header(404);
nocache_headers();
