<?php

use ImMcpProvider\Module;

include_once __DIR__ . '/header.php';

/** @var mod_mcpapi_SkillHandler $handler */
$handler = Module::handler('skill');
$operation = isset($_REQUEST['op']) ? preg_replace('/[^a-z_]/', '', $_REQUEST['op']) : 'list';

icms_cp_header();
icms::$module->displayAdminMenu(4, _MI_MCPAPI_MENU_SKILLS);

if ($operation === 'save') {
    if (! icms::$security->check()) {
        redirect_header(Module::url('admin/skill.php'), 3, implode(', ', icms::$security->getErrors()));
    }

    $object = $handler->create();
    $object->setVar('name', trim((string) ($_POST['name'] ?? '')));
    $object->setVar('title', trim((string) ($_POST['title'] ?? '')));
    $object->setVar('instructions', trim((string) ($_POST['instructions'] ?? '')));
    $object->setVar('dirname', trim((string) ($_POST['dirname'] ?? '')));

    if ($object->getVar('name', 'n') === '' || $object->getVar('title', 'n') === '' || $object->getVar('instructions', 'n') === '') {
        redirect_header(Module::url('admin/skill.php'), 3, _CO_ICMS_SAVE_ERROR);
    }

    if (! $handler->insert($object, true)) {
        redirect_header(Module::url('admin/skill.php'), 3, _CO_ICMS_SAVE_ERROR . $object->getHtmlErrors());
    }

    redirect_header(Module::url('admin/skill.php'), 2, _CO_ICMS_SAVE_SUCCESS);
    exit();
}

if ($operation === 'del') {
    $skillId = (int) ($_REQUEST['skill_id'] ?? 0);
    $object = $handler->get($skillId);

    if ($object->isNew()) {
        redirect_header(Module::url('admin/skill.php'), 3, _CO_ICMS_NOT_SELECTED);
    }

    $confirm = isset($_POST['confirm']) ? (int) $_POST['confirm'] : 0;

    if ($confirm) {
        if (! icms::$security->check()) {
            redirect_header(Module::url('admin/skill.php'), 3, implode(', ', icms::$security->getErrors()));
        }

        $handler->delete($object);

        redirect_header(Module::url('admin/skill.php'), 2, _CO_ICMS_DELETE_SUCCESS);
        exit();
    }

    icms_core_Message::confirm(
        ['op' => 'del', 'skill_id' => $skillId, 'confirm' => 1],
        Module::url('admin/skill.php'),
        _AM_MCPAPI_SKILL_DELETE_CONFIRM,
        _CO_ICMS_DELETE
    );
    icms_cp_footer();
    exit();
}

if ($operation === 'new') {
    echo '<h3>' . _AM_MCPAPI_SKILL_ADD . '</h3>';
    echo '<form method="post" action="' . Module::url('admin/skill.php') . '">';
    echo '<input type="hidden" name="op" value="save" />';
    echo icms::$security->getTokenHTML();
    echo '<table class="outer" cellspacing="1">';
    echo '<tr><th>' . _AM_MCPAPI_SKILL_NAME . '</th><td class="even"><input type="text" name="name" required="required" /></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_SKILL_TITLE . '</th><td class="odd"><input type="text" name="title" required="required" /></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_SKILL_INSTRUCTIONS . '</th><td class="even"><textarea name="instructions" rows="8" cols="60" required="required"></textarea></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_SKILL_MODULE . '</th><td class="odd"><input type="text" name="dirname" /></td></tr>';
    echo '</table>';
    echo '<input type="submit" value="' . _AM_MCPAPI_SKILL_ADD . '" />';
    echo '</form>';
    icms_cp_footer();
    exit();
}

echo '<p><a href="' . Module::url('admin/skill.php?op=new') . '">' . _AM_MCPAPI_SKILL_ADD . '</a></p>';
echo '<table class="outer" cellspacing="1">';
echo '<tr class="head">'
    . '<th>' . _AM_MCPAPI_SKILL_NAME . '</th>'
    . '<th>' . _AM_MCPAPI_SKILL_TITLE . '</th>'
    . '<th>' . _AM_MCPAPI_CONTEXT_MODULE . '</th>'
    . '<th>&nbsp;</th>'
    . '</tr>';

$criteria = new icms_db_criteria_Compo();
$criteria->setSort('name');
$criteria->setOrder('ASC');

$rowClass = 'odd';

foreach ($handler->getObjects($criteria) as $skill) {
    $rowClass = $rowClass === 'odd' ? 'even' : 'odd';

    echo '<tr>';
    echo '<td class="' . $rowClass . '">' . htmlspecialchars((string) $skill->getVar('name', 'n'), ENT_QUOTES) . '</td>';
    echo '<td class="' . $rowClass . '">' . htmlspecialchars((string) $skill->getVar('title', 'n'), ENT_QUOTES) . '</td>';
    echo '<td class="' . $rowClass . '">' . htmlspecialchars((string) $skill->getVar('dirname', 'n'), ENT_QUOTES) . '</td>';
    echo '<td class="' . $rowClass . '"><a href="' . Module::url('admin/skill.php?op=del&skill_id=' . $skill->getVar('skill_id', 'n')) . '">' . _CO_ICMS_DELETE . '</a></td>';
    echo '</tr>';
}

echo '</table>';

icms_cp_footer();
