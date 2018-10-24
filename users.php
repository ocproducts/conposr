<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

session_start();

function is_guest()
{
    return (db_get_first_id() == get_member());
}

function get_member()
{
    if (!isset($_SESSION['member_id'])) {
        return db_get_first_id();
    }
    return $_SESSION['member_id'];
}

function get_username($id)
{
    if (!isset($_SESSION['member_id'])) {
        return 'Guest';
    }
    return $_SESSION['username'];
}

function get_member_email_address($id)
{
    if (!isset($_SESSION['member_id'])) {
        return '';
    }
    return $_SESSION['email'];
}

function is_admin($id)
{
    if (!isset($_SESSION['member_id'])) {
        return '';
    }
    return $_SESSION['is_admin'];
}

function get_member_row($member)
{
    if (!isset($_SESSION['member_id'])) {
        return '';
    }
    return $_SESSION['member_row'];
}
