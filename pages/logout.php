<?php
/**
 * TaskFlow — Sign out
 */

logout_user();
session_boot();
flash_info('You have been signed out. See you soon!');
redirect(url('index.php', ['page' => 'login']));
