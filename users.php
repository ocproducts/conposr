<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

session_cache_limiter('private');
session_start();

function is_guest($member_id = null)
{
    if ($member_id === null) {
        $member_id = get_member();
    }
    return (intval(get_option('guest_id')) == $member_id);
}

function get_member()
{
    if (!isset($_SESSION['member_id'])) {
        return intval(get_option('guest_id'));
    }
    return $_SESSION['member_id'];
}

function get_username()
{
    if (!isset($_SESSION['username'])) {
        return 'Guest';
    }
    return $_SESSION['username'];
}

function get_member_email_address()
{
    if (!isset($_SESSION['email'])) {
        return '';
    }
    return $_SESSION['email'];
}

function is_admin()
{
    if (!isset($_SESSION['is_admin'])) {
        return false;
    }
    return $_SESSION['is_admin'];
}

function get_member_row()
{
    if (!isset($_SESSION['member_row'])) {
        return array();
    }
    return $_SESSION['member_row'];
}
