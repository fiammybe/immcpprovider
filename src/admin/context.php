<?php

use ImMcpProvider\Module;

include_once __DIR__ . '/header.php';

/** @var mod_mcpapi_ContextHandler $handler */
$handler = Module::handler('context');
$operation = isset($_REQUEST['op']) ? preg_replace('/[^a-z_]/', '', $_REQUEST['op']) : 'list';

icms_cp_header();
icms::$module->displayAdminMenu(3, _MI_MCPAPI_MENU_CONTEXT);

if ($operation === 'save') {
    if (! icms::$security->check()) {
        redirect_header(Module::url('admin/context.php'), 3, implode(', ', icms::$security->getErrors()));
    }

    $object = $handler->create();
    $object->setVar('dirname', trim((string) ($_POST['dirname'] ?? '')));
    $scope = (string) ($_POST['scope'] ?? mod_mcpapi_Context::SCOPE_MODULE);
    $object->setVar('scope', in_array($scope, [mod_mcpapi_Context::SCOPE_MODULE, mod_mcpapi_Context::SCOPE_HANDLER, mod_mcpapi_Context::SCOPE_FIELD], true) ? $scope : mod_mcpapi_Context::SCOPE_MODULE);
    $object->setVar('handler', trim((string) ($_POST['handler'] ?? '')));
    $object->setVar('field', trim((string) ($_POST['field'] ?? '')));
    $object->setVar('description', trim((string) ($_POST['description'] ?? '')));
    $object->setVar('relation_module', trim((string) ($_POST['relation_module'] ?? '')));
    $object->setVar('relation_handler', trim((string) ($_POST['relation_handler'] ?? '')));
    $object->setVar('source', mod_mcpapi_Context::SOURCE_ADMIN);

    if ($object->getVar('dirname', 'n') === '') {
        redirect_header(Module::url('admin/context.php'), 3, _CO_ICMS_SAVE_ERROR);
    }

    if (! $handler->insert($object, true)) {
        redirect_header(Module::url('admin/context.php'), 3, _CO_ICMS_SAVE_ERROR . $object->getHtmlErrors());
    }

    redirect_header(Module::url('admin/context.php'), 2, _CO_ICMS_SAVE_SUCCESS);
    exit();
}

if ($operation === 'del') {
    $contextId = (int) ($_REQUEST['context_id'] ?? 0);
    $object = $handler->get($contextId);

    if ($object->isNew()) {
        redirect_header(Module::url('admin/context.php'), 3, _CO_ICMS_NOT_SELECTED);
    }

    if ($object->isFromModule()) {
        redirect_header(Module::url('admin/context.php'), 3, _AM_MCPAPI_CONTEXT_READONLY_HELP);
    }

    $confirm = isset($_POST['confirm']) ? (int) $_POST['confirm'] : 0;

    if ($confirm) {
        if (! icms::$security->check()) {
            redirect_header(Module::url('admin/context.php'), 3, implode(', ', icms::$security->getErrors()));
        }

        $handler->delete($object);

        redirect_header(Module::url('admin/context.php'), 2, _CO_ICMS_DELETE_SUCCESS);
        exit();
    }

    icms_core_Message::confirm(
        ['op' => 'del', 'context_id' => $contextId, 'confirm' => 1],
        Module::url('admin/context.php'),
        _AM_MCPAPI_CONTEXT_DELETE_CONFIRM,
        _CO_ICMS_DELETE
    );
    icms_cp_footer();
    exit();
}

if ($operation === 'new') {
    echo '<h3>' . _AM_MCPAPI_CONTEXT_ADD . '</h3>';
    echo '<form method="post" action="' . Module::url('admin/context.php') . '">';
    echo '<input type="hidden" name="op" value="save" />';
    echo icms::$security->getTokenHTML();
    echo '<table class="outer" cellspacing="1">';
    echo '<tr><th>' . _AM_MCPAPI_CONTEXT_MODULE . '</th><td class="even"><input type="text" name="dirname" required="required" /></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_CONTEXT_SCOPE . '</th><td class="odd"><select name="scope">'
        . '<option value="' . mod_mcpapi_Context::SCOPE_MODULE . '">' . mod_mcpapi_Context::SCOPE_MODULE . '</option>'
        . '<option value="' . mod_mcpapi_Context::SCOPE_HANDLER . '">' . mod_mcpapi_Context::SCOPE_HANDLER . '</option>'
        . '<option value="' . mod_mcpapi_Context::SCOPE_FIELD . '">' . mod_mcpapi_Context::SCOPE_FIELD . '</option>'
        . '</select></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_CONTEXT_HANDLER . '</th><td class="even"><input type="text" name="handler" /></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_CONTEXT_FIELD . '</th><td class="odd"><input type="text" name="field" /></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_CONTEXT_DESCRIPTION . '</th><td class="even"><textarea name="description" rows="4" cols="60"></textarea></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_CONTEXT_RELATION_MODULE . '</th><td class="odd"><input type="text" name="relation_module" /></td></tr>';
    echo '<tr><th>' . _AM_MCPAPI_CONTEXT_RELATION_HANDLER . '</th><td class="even"><input type="text" name="relation_handler" /></td></tr>';
    echo '</table>';
    echo '<input type="submit" value="' . _AM_MCPAPI_CONTEXT_ADD . '" />';
    echo '</form>';
    icms_cp_footer();
    exit();
}

echo '<p><a href="' . Module::url('admin/context.php?op=new') . '">' . _AM_MCPAPI_CONTEXT_ADD . '</a></p>';
echo '<table class="outer" cellspacing="1">';
echo '<tr class="head">'
    . '<th>' . _AM_MCPAPI_CONTEXT_MODULE . '</th>'
    . '<th>' . _AM_MCPAPI_CONTEXT_SCOPE . '</th>'
    . '<th>' . _AM_MCPAPI_CONTEXT_HANDLER . '</th>'
    . '<th>' . _AM_MCPAPI_CONTEXT_FIELD . '</th>'
    . '<th>' . _AM_MCPAPI_CONTEXT_DESCRIPTION . '</th>'
    . '<th>' . _AM_MCPAPI_CONTEXT_SOURCE . '</th>'
    . '<th>&nbsp;</th>'
    . '</tr>';

$criteria = new icms_db_criteria_Compo();
$criteria->setSort('dirname');
$criteria->setOrder('ASC');

$rowClass = 'odd';

foreach ($handler->getObjects($criteria) as $entry) {
    $rowClass = $rowClass === 'odd' ? 'even' : 'odd';
    $isFromModule = $entry->isFromModule();

    echo '<tr>';
    echo '<td class="' . $rowClass . '">' . htmlspecialchars((string) $entry->getVar('dirname', 'n'), ENT_QUOTES) . '</td>';
    echo '<td class="' . $rowClass . '">' . htmlspecialchars((string) $entry->getVar('scope', 'n'), ENT_QUOTES) . '</td>';
    echo '<td class="' . $rowClass . '">' . htmlspecialchars((string) $entry->getVar('handler', 'n'), ENT_QUOTES) . '</td>';
    echo '<td class="' . $rowClass . '">' . htmlspecialchars((string) $entry->getVar('field', 'n'), ENT_QUOTES) . '</td>';
    echo '<td class="' . $rowClass . '">' . htmlspecialchars((string) $entry->getVar('description', 'n'), ENT_QUOTES) . '</td>';
    echo '<td class="' . $rowClass . '">' . ($isFromModule ? _AM_MCPAPI_CONTEXT_SOURCE_MODULE : _AM_MCPAPI_CONTEXT_SOURCE_ADMIN) . '</td>';
    echo '<td class="' . $rowClass . '">' . ($isFromModule
        ? ''
        : '<a href="' . Module::url('admin/context.php?op=del&context_id=' . $entry->getVar('context_id', 'n')) . '">' . _CO_ICMS_DELETE . '</a>') . '</td>';
    echo '</tr>';
}

echo '</table>';

icms_cp_footer();
