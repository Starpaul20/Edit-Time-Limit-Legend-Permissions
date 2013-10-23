<?php
/**
 * Edit Time Limit/Legend Permissions
 * Copyright 2011 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(my_strpos($_SERVER['PHP_SELF'], 'editpost.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'editpost_editedby';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("datahandler_post_update", "editperms_run");
$plugins->add_hook("postbit", "editperms_postbit");
$plugins->add_hook("editpost_start", "editperms_edit_page");

$plugins->add_hook("admin_formcontainer_output_row", "editperms_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "editperms_usergroup_permission_commit");

// The information that shows up on the plugin manager
function editperms_info()
{
	return array(
		"name"				=> "Edit Time Limit/Legend Permissions",
		"description"		=> "Adds two usergroup permissions for edit time limit and removing Edited by legend.",
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0.2",
		"guid"				=> "809da2afc38a36394973128975349daf",
		"compatibility"		=> "16*"
	);
}

// This function runs when the plugin is installed.
function editperms_install()
{
	global $db, $cache;
	editperms_uninstall();

	$db->add_column("usergroups", "canremoveeditedby", "int(1) NOT NULL default '0'");
	$db->add_column("usergroups", "edittimelimit", "int(3) NOT NULL default '0'");

	$db->add_column("posts", "disableeditedby", "int(1) NOT NULL default '0'");

	$cache->update_usergroups();
}

// Checks to make sure plugin is installed
function editperms_is_installed()
{
	global $db;
	if($db->field_exists("canremoveeditedby", "usergroups"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function editperms_uninstall()
{
	global $db, $cache;

	if($db->field_exists("canremoveeditedby", "usergroups"))
	{
		$db->drop_column("usergroups", "canremoveeditedby");
	}

	if($db->field_exists("edittimelimit", "usergroups"))
	{
		$db->drop_column("usergroups", "edittimelimit");
	}

	if($db->field_exists("disableeditedby", "posts"))
	{
		$db->drop_column("posts", "disableeditedby");
	}

	$cache->update_usergroups();
}

// This function runs when the plugin is activated.
function editperms_activate()
{
	global $db;

	$insert_array = array(
		'title'		=> 'editpost_editedby',
		'template'	=> $db->escape_string('<td class="trow2"><strong>{$lang->edited_by}</strong></td>
<td class="trow2"><span class="smalltext">
<label><input type="checkbox" class="checkbox" name="disableeditedby" value="1" tabindex="7" {$disableeditedby} /> {$lang->disable_edited_by}</label></span>
</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("editpost", "#".preg_quote('{$pollbox}')."#i", '{$pollbox}{$editedby}');
}

// This function runs when the plugin is deactivated.
function editperms_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('editpost_editedby')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("editpost", "#".preg_quote('{$editedby}')."#i", '', 0);
}

// Update 'Edited by' input from edit page
function editperms_run()
{
	global $db, $mybb, $post;
	$edit = get_post($post['pid']);

	$editedby = array(
		"disableeditedby" => intval($mybb->input['disableeditedby']),
	);
	$db->update_query("posts", $editedby, "pid='{$edit['pid']}'");
}

// Remove edited by on posts if option says so
function editperms_postbit($post)
{
	global $db, $mybb, $fid;
	if($post['disableeditedby'] == 1)
	{
		$post['editedmsg'] = "";
	}

	if($mybb->usergroup['edittimelimit'] > 0 && !is_moderator($fid, "caneditposts"))
	{
		$edittime = TIME_NOW - (60 * $mybb->usergroup['edittimelimit']);
		if($post['dateline'] < $edittime)
		{
			$post['button_edit'] = "";
		}
	}

	return $post;
}

// Edit page options
function editperms_edit_page()
{
	global $db, $mybb, $templates, $lang, $editedby, $disableeditedby;
	$lang->load("editperms");
	$edit = get_post($mybb->input['pid']);

	if($mybb->usergroup['edittimelimit'] > 0 && !is_moderator($edit['fid'], "caneditposts"))
	{
		$edittime = TIME_NOW - (60 * $mybb->usergroup['edittimelimit']);
		if($edit['dateline'] < $edittime)
		{
			error_no_permission();
		}
	}

	if($mybb->usergroup['canremoveeditedby'] == 1)
	{
		if($edit['disableeditedby'] == 1)
		{
			$disableeditedby = "checked=\"checked\"";
		}
		else
		{
			$disableeditedby = "";
		}
		eval("\$editedby = \"".$templates->get("editpost_editedby")."\";");
	}
}

// Admin CP permission control
function editperms_usergroup_permission($above)
{
	global $mybb, $lang, $form;
	$lang->load("editperms", true);

	if($above['title'] == $lang->editing_deleting_options && $lang->editing_deleting_options)
	{
		$above['content'] .= "<div class=\"group_settings_bit\">".$form->generate_check_box("canremoveeditedby", 1, $lang->can_remove_edited_by, array("checked" => $mybb->input['canremoveeditedby']))."</div>";
		$above['content'] .= "<div class=\"group_settings_bit\">{$lang->edit_time_limit}:<br /><small>{$lang->edit_time_limit_desc}</small><br /></div>".$form->generate_text_box('edittimelimit', $mybb->input['edittimelimit'], array('id' => 'edittimelimit', 'class' => 'field50'));
	}

	return $above;
}

function editperms_usergroup_permission_commit()
{
	global $mybb, $updated_group;
	$updated_group['canremoveeditedby'] = intval($mybb->input['canremoveeditedby']);
	$updated_group['edittimelimit'] = intval($mybb->input['edittimelimit']);
}

?>