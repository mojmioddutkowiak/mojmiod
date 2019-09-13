<?php
/**
 * 2002-2015 TemplateMonster
 *
 * TM Mega Layout
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the General Public License (GPL 2.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/GPL-2.0
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the module to newer
 * versions in the future.
 *
 *  @author    TemplateMonster (Alexander Grosul & Alexander Pervakov)
 *  @copyright 2002-2015 TemplateMonster
 *  @license   http://opensource.org/licenses/GPL-2.0 General Public License (GPL 2.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once _PS_MODULE_DIR_ . 'tmmegalayout/classes/TMMegaLayoutItems.php';
include_once _PS_MODULE_DIR_ . 'tmmegalayout/classes/TMMegaLayoutLayouts.php';
include_once _PS_MODULE_DIR_ . 'tmmegalayout/classes/TMMegaLayoutExport.php';
include_once _PS_MODULE_DIR_ . 'tmmegalayout/classes/TMMegaLayoutImport.php';

class Tmmegalayout extends Module
{
    protected $id_shop;
    protected $html;
    protected $errors;
    public $warning;
    public $defLayoutHooks;
    protected $defLayoutPath;
    protected $defCleanFolders;
    public $style_path;
    protected $php_compatibility = true;

    public function __construct()
    {
        $this->name = 'tmmegalayout';
        $this->tab = 'front_office_features';
        $this->version = '0.0.1';
        $this->author = 'TemplateMonster (Alexander Grosul & Alexander Pervakov)';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('TM Mega Layout');
        $this->description = $this->l('Module adds more functionality for hooks.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        $this->id_shop = $this->context->shop->id;
        $this->defLayoutHooks = array(
            'displayTopColumn',
            'displayHome',
            'displayFooter'
        );

        $this->errors = '';
        $this->style_path = $this->local_path . '/views/css/items/';
        $this->defLayoutPath = $this->local_path . 'default/';

        $this->defCleanFolders = array(
            //css folder
            $this->style_path,
            //export folder
            $this->local_path . 'export/',
            //import folder
            $this->local_path . 'import/'
        );
        if (version_compare(PHP_VERSION, '5.3.0', '<')) {
            $this->php_compatibility = false;
        }
    }

    public function install()
    {
        if (!$this->php_compatibility) {
            $this->_errors[] = $this->l('PHP version must be 5.3 or higher');
            return false;
        }
        include(dirname(__FILE__) . '/sql/install.php');

        return parent::install() &&
                $this->installDefLayouts() &&
                $this->createAjaxController() &&
                $this->registerHook('header') &&
                $this->registerHook('backOfficeHeader') &&
                $this->registerHook('tmMegaLayoutTopColumn') &&
                $this->registerHook('tmMegaLayoutHome') &&
                $this->registerHook('tmMegaLayoutFooter') &&
                $this->registerHook('actionObjectShopAddAfter');
    }

    public function uninstall()
    {
        include(dirname(__FILE__) . '/sql/uninstall.php');

        return parent::uninstall() &&
                $this->removeAjaxContoller() &&
                $this->cleanFolders($this->defCleanFolders);
    }

    public function createAjaxController()
    {
        $tab = new Tab();
        $tab->active = 1;
        $languages = Language::getLanguages(false);

        if (is_array($languages)) {
            foreach ($languages as $language) {
                $tab->name[$language['id_lang']] = 'tmmegalayout';
            }
        }

        $tab->class_name = 'AdminTMMegaLayout';
        $tab->module = $this->name;
        $tab->id_parent = - 1;

        return (bool) $tab->add();
    }

    private function removeAjaxContoller()
    {
        if ($tab_id = (int) Tab::getIdFromClassName('AdminTMMegaLayout')) {
            $tab = new Tab($tab_id);
            $tab->delete();
        }

        return true;
    }

    /**
     * Install default layouts from 'default' folder
     * 
     * @return bool true
     */
    public function installDefLayouts()
    {
        $path = $this->defLayoutPath;
        $files = scandir($path);

        foreach ($files as $file) {
            if (($file != '..') && ($file != '.') && (TMMegaLayoutImport::isZip($file))) {
                $import = new TMMegaLayoutImport();
                $import->importLayout($path, $file, true);
            }
        }

        return true;
    }

    public function getContent()
    {
        $this->html = '';

        if (Shop::getContext() == Shop::CONTEXT_GROUP || Shop::getContext() == Shop::CONTEXT_ALL) {
            $this->errors .= $this->displayError($this->l('You cannot add/edit elements from \"All Shops\" or  \"Group Shop\" context'));
        } else {
            // 'tab_params' for create admin panel on load
            $this->context->smarty->assign(array(
                'templates_dir' => _PS_MODULE_DIR_ . 'tmmegalayout/views/templates/admin/',
                'theme_url' => $this->context->link->getAdminLink('AdminTMMegaLayout'),
                'tabs' => $this->getTabsConfig(),
            ));

            $this->html .= $this->display(__FILE__, 'views/templates/admin/tmmegalayout.tpl');
        }

        return $this->errors . $this->warning . $this->html;
    }

    /**
     * Clean folders from array
     * 
     * @param array $folders_array Array of folders to clean
     * @return true
     */
    protected function cleanFolders($folders_array)
    {
        foreach ($folders_array as $folder) {
            Tmmegalayout::cleanFolder($folder);
        }
        return true;
    }

    /**
     * Clean folder 
     * 
     * @param string $path Folder to clean
     * @return bool true
     */
    public static function cleanFolder($path)
    {
        $files = scandir($path);

        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && $file != 'index.php') {
                Tmmegalayout::checkPerms($path . $file, 0777);

                if (is_dir($path . $file)) {
                    Tmmegalayout::cleanFolder($path . $file . '/');
                    rmdir($path . $file);
                } else {
                    unlink($path . $file);
                }
            }
        }

        return true;
    }

    /**
     * Get array of modules in hook
     * 
     * @param int $id_hook Hook id
     * @param int $id_layout Layout id
     * @return array List of modules
     */
    public function getHookModulesList($id_hook, $id_layout)
    {
        $list = array();
        $modules_list = Hook::getModulesFromHook((int) $id_hook);
        $used_modules_list = TMMegaLayoutItems::checkModuleInLayout($id_layout);
        $i = 0;

        foreach ($modules_list as $module) {
            $name = Module::getInstanceById($module['id_module'])->name;
            $display_name = Module::getInstanceById($module['id_module'])->displayName;
            // check if module is active for this store and don\'t used yet
            if (!$this->checkModuleStatus($name)) {
                if (!count($used_modules_list) || (count($used_modules_list) && !in_array($name, $used_modules_list))) {
                    $list[$i]['id'] = $module['id_module'];
                    $list[$i]['name'] = $name;
                    $list[$i]['public_name'] = $display_name;
                }
                $i++;
            }
        }

        return $list;
    }

    /**
     * @return string Html of tools list
     */
    protected function renderToolsList()
    {
        $tools = array(
            'Export',
            'Import',
            'Reset'
        );

        $this->context->smarty->assign('tools', $tools);

        return $this->display($this->_path, '/views/templates/admin/tmmegalayout_tools.tpl');
    }

    /**
     * 
     * @param string $tool_name
     * @return string Html of tool
     */
    public function renderToolContent($tool_name)
    {
        switch ($tool_name) {
            case 'Export':
                return $this->renderExportContent();
            case 'Import':
                return $this->renderImportContent();
            case 'Reset':
                return $this->renderResetContent();
        }
    }

    /**
     * @return string Html of export page
     */
    protected function renderExportContent()
    {
        $hooks = $this->getExportConfig();
        $this->context->smarty->assign('hooks', $hooks);

        return $this->display($this->_path, '/views/templates/admin/tools/export.tpl');
    }

    /**
     * @return string Html of import page
     */
    protected function renderImportContent()
    {
        $this->context->smarty->assign('max_file_size', Tmmegalayout::getMaxFileSize());

        return $this->display($this->_path, '/views/templates/admin/tools/import.tpl');
    }

    /**
     * @return string Html of reset page
     */
    protected function renderResetContent()
    {
        return $this->display($this->_path, '/views/templates/admin/tools/reset.tpl');
    }

    /**
     * @return array of export configs
     */
    protected function getExportConfig()
    {
        $hooks = array();

        foreach ($this->defLayoutHooks as $hook_name) {
            if (!$id_hook = Hook::getIdByName($hook_name)) {
                return $this->errors .= $this->displayError($this->l('Hook does not exist'));
            }

            $status = 'on';

            if (!TMMegaLayoutLayouts::getLayoutsForHook($id_hook, $this->id_shop)) {
                $status = 'off';
            }

            $hooks[] = array(
                'hook_name' => $hook_name,
                'layouts' => TMMegaLayoutLayouts::getLayoutsForHook($id_hook, $this->id_shop),
                'status' => $status
            );
        }

        return $hooks;
    }

    /**
     * Sort array by field 'sort_order'
     * 
     * @param array $array
     * @return array
     */
    protected function arraySort($array)
    {
        if (count($array) > 1) {
            usort($array, function ($a, $b) {
                return $a['sort_order'] - $b['sort_order'];
            });
        }

        return $array;
    }

    /**
     * Get layouts array from db
     * 
     * @param int $id_layout
     * @return bool|array false|array of layouts
     */
    public function getLayoutItems($id_layout)
    {
        $items = null;
        if (!$result = TMMegaLayoutItems::getItems($id_layout)) {
            return false;
        }

        foreach ($result as $item) {
            $id_item = $item['id_item'];
            $items[$id_item] = $item;
        }

        return $items;
    }

    /**
     * Generate layout map
     * 
     * @param array $layout_items
     * @return array layout map
     */
    public function generateLayoutMap($layout_items)
    {
        $map = array();

        if (is_array($layout_items)) {
            foreach ($layout_items as $id => $item) {
                $id_parent = $item['id_parent'];
                $level = $this->checkLayoutItemLevel($layout_items, $id);
                $map[$level][$id_parent][] = $item;
            }
        }

        return $map;
    }

    /**
     * Check level of item
     * 
     * @param array $layout_items
     * @param int $id_item
     * @param int $level
     * @return int item level in array
     */
    protected function checkLayoutItemLevel($layout_items, $id_item, $level = 0)
    {
        if ($layout_items[$id_item]['id_parent'] != 0) {
            $id_parent = $layout_items[$id_item]['id_parent'];

            if (isset($layout_items[$id_parent]['id_parent'])) {
                $level++;
                $level = $this->checkLayoutItemLevel($layout_items, $id_parent, $level);
            }
        }

        return $level;
    }

    /**
     * Get module html
     * 
     * @param string $hook_name
     * @param id $id_module
     * @return boolean|string False|Module html
     */
    protected function renderModuleContent($hook_name, $id_module)
    {
        if (!$result = Hook::exec($hook_name, array(), $id_module)) {
            return false;
        }

        return $result;
    }

    /**
     * @param array $map
     * @param string $hook_name
     * @param int $level
     * @param array $positions
     * @return bool|string False or Layout html
     */
    protected function renderLayoutFront($map, $hook_name, $level = null, $positions = array())
    {
        if (is_null($level)) {
            $level = count($map) - 1;

            if ($level < 0) {
                return false;
            }
        }

        foreach ($map[$level] as $id_parent => $items) {
            $positions[$id_parent] = '';
            $items = $this->arraySort($items);

            foreach ($items as $item) {
                if (!isset($positions[$item['id_item']])) {
                    $positions[$item['id_item']] = '';
                }

                switch ($item['type']) {
                    case 'module':
                        $id_module = Module::getModuleIdByName($item['module_name']);
                        $positions[$id_parent] .= $this->renderModuleContent($hook_name, $id_module);
                        break;
                    case 'wrapper':
                        $this->context->smarty->assign(array(
                            'position' => $positions[$item['id_item']],
                            'tmml_class' => 'wrapper ' . $item['id_unique'] . ' ' . $item['specific_class']
                        ));
                        $positions[$id_parent] .= $this->display($this->_path, '/views/templates/hook/layouts/layout.tpl');
                        break;
                    case 'row':
                        $this->context->smarty->assign(array(
                            'position' => $positions[$item['id_item']],
                            'tmml_class' => 'row ' . $item['id_unique'] . ' ' . $item['specific_class']
                        ));
                        $positions[$id_parent] .= $this->display($this->_path, '/views/templates/hook/layouts/layout.tpl');
                        break;
                    case 'col':
                        $class = $item['id_unique'] . ' ' . $item['col_xs'] . ' ' . $item['col_sm'] . ' ' . $item['col_md'] . ' ' . $item['col_lg'] . ' ';
                        $this->context->smarty->assign(array(
                            'position' => $positions[$item['id_item']],
                            'tmml_class' => $class . $item['specific_class']
                        ));
                        $positions[$id_parent] .= $this->display($this->_path, '/views/templates/hook/layouts/layout.tpl');
                        break;
                }
            }
        }

        $level--;

        if ($level >= 0) {
            $html = $this->renderLayoutFront($map, $hook_name, $level, $positions);
        } else {
            $html = $positions[0];
        }

        return $html;
    }

    /**
     * Generate layout html for backoffice
     *
     * @param array $map Map of layout
     * @param bool $preview Layout mod
     * @param int $level Level of layout item
     * @param array $positions Items positions in layout
     * @return string Html of layout
     */
    public function renderLayoutAdmin($map, $preview = false, $level = null, $positions = array())
    {
        if (count($map) <= 0) {
            return false;
        }

        if (is_null($level)) {
            $level = count($map) - 1;
        }

        foreach ($map[$level] as $id_parent => $items) {
            $positions[$id_parent] = '';
            $items = $this->arraySort($items);

            foreach ($items as $item) {
                if (!isset($positions[$item['id_item']])) {
                    $positions[$item['id_item']] = '';
                }

                switch ($item['type']) {
                    case 'module':
                        if ($warning = $this->checkModuleStatus($item['module_name'])) {
                            $item['warning'] = $warning;
                        }
                        $this->context->smarty->assign(array(
                            'elem' => $item,
                            'preview' => $preview
                        ));
                        $positions[$id_parent] .= $this->display($this->_path, '/views/templates/admin/layouts/module.tpl');
                        break;
                    case 'wrapper':
                        $this->context->smarty->assign(array(
                            'position' => $positions[$item['id_item']],
                            'elem' => $item,
                            'preview' => $preview
                        ));
                        $positions[$id_parent] .= $this->display($this->_path, '/views/templates/admin/layouts/wrapper.tpl');
                        break;
                    case 'row':
                        $this->context->smarty->assign(array(
                            'position' => $positions[$item['id_item']],
                            'elem' => $item,
                            'preview' => $preview
                        ));
                        $positions[$id_parent] .= $this->display($this->_path, '/views/templates/admin/layouts/row.tpl');
                        break;
                    case 'col':
                        $this->context->smarty->assign(array(
                            'class' => $item['col_xs'] . ' ' . $item['col_sm'] . ' ' . $item['col_md'] . ' ' . $item['col_lg'],
                            'position' => $positions[$item['id_item']],
                            'elem' => $item,
                            'preview' => $preview
                        ));
                        $positions[$id_parent] .= $this->display($this->_path, '/views/templates/admin/layouts/col.tpl');
                        break;
                }
            }
        }

        $level--;

        if ($level >= 0) {
            $html = $this->renderLayoutAdmin($map, $preview, $level, $positions);
        } else {
            $html = $positions[0];
        }

        return $html;
    }

    /**
     * Get layout html for backoffice
     *
     * @param int $id_layout
     * @param bool $preview Preview or not
     * @return string Html of layout
     */
    public function getLayoutAdmin($id_layout, $preview = false)
    {
        $layout_array = $this->getLayoutItems($id_layout);
        $map = $this->generateLayoutMap($layout_array);

        $result = $this->renderLayoutAdmin($map, $preview);
        if ($preview && !$result) {
            return $this->displayWarning($this->l('Add some items to layout.'));
        }
        return $result;
    }

    /**
     * Get tabs config
     *
     * @return string Tabs html
     */
    public function getTabsConfig()
    {
        $tabs = array();
        foreach ($this->defLayoutHooks as $hook_name) {
            if (!$id_hook = Hook::getIdByName($hook_name)) {
                return $this->errors .= $this->displayError($this->l('Hook does not exist'));
            }
            $tabs = array_merge($tabs, $this->getLayoutTabConfig($id_hook));
        }

        $tabs['Tools'] = array(
            'content' => $this->renderToolsList(),
            'type' => 'settings',
            'id' => 'tmml-tools_tab'
        );

        return $tabs;
    }

    /**
     * Return layout content or backoffice
     *
     * @param string $id_layout
     * @return string Html of layout content
     */
    public function renderLayoutContent($id_layout)
    {
        $tab = new TMMegaLayoutLayouts($id_layout);
        $this->context->smarty->assign('content', array(
            'layout' => $this->getLayoutAdmin($id_layout),
            'id_layout' => $id_layout,
            'id_hook' => $tab->id_hook,
            'status' => $tab->status,
            'layout_name' => $tab->layout_name
        ));

        $layout_content = $this->display($this->_path, '/views/templates/admin/tmmegalayout-layout-content.tpl');
        $layout_buttons = $this->display($this->_path, '/views/templates/admin/tmmegalayout-layout-buttons.tpl');

        return array($layout_content, $layout_buttons);
    }

    /**
     * Return config for layout tab
     *
     * @param int $id_hook
     * @return string Tab html
     */
    protected function getLayoutTabConfig($id_hook)
    {
        $tab_array = array();
        $hook_name = Hook::getNameById($id_hook);
        $id_layout = TMMegaLayoutLayouts::getActiveLayoutId($id_hook, $this->id_shop);
        $layouts_list = TMMegaLayoutLayouts::getLayoutsForHook($id_hook, $this->id_shop);

        if (!$id_layout || !$layouts_list) {
            $layout = null;
        } else {
            $layout = $this->getLayoutAdmin($id_layout);
        }

        $tab = new TMMegaLayoutLayouts($id_layout);
        $tab_array[$hook_name] = array(
            'layouts_list' => $layouts_list,
            'layouts_list_json' => Tools::jsonEncode($layouts_list),
            'layout' => $layout,
            'id_layout' => $id_layout,
            'id_hook' => $id_hook,
            'type' => 'layout',
            'status' => $tab->status,
            'id' => '',
            'layout_name' => $tab->layout_name
        );
        
        return $tab_array;
    }

    /**
     * Render layout tab
     *
     * @param int $id_hook
     * @return string Layout tab html
     */
    public function renderLayoutTab($id_hook)
    {
        $tab = $this->getLayoutTabConfig($id_hook);
        $hook_name = Hook::getNameById($id_hook);
        $this->context->smarty->assign(array(
            'content' => $tab[$hook_name],
            'templates_dir' => _PS_MODULE_DIR_ . 'tmmegalayout/views/templates/admin/',
        ));
        
        return $this->display($this->_path, '/views/templates/admin/tmmegalayout-tab-content.tpl');
    }

    /**
     * Generate html of hook
     * 
     * @param int $hook_name
     * @return html Layout html
     */
    public function getLayoutFront($hook_name)
    {
        if (!$id_hook = Hook::getIdByName($hook_name)) {
            return $this->errors .= $this->displayError($this->l('Hook does not exist'));
        }

        if (!$id_active_layout = TMMegaLayoutLayouts::getActiveLayoutId($id_hook, $this->id_shop)) {
            return false;
        }

        $layouts_array = $this->getLayoutItems($id_active_layout);
        $map = $this->generateLayoutMap($layouts_array);

        if (count($layouts_array) > 0) {
            return $this->renderLayoutFront($map, $hook_name);
        } else {
            return false;
        }
    }

    /**
     * Return id shop
     *
     * @return int Shop id
     */
    public function getIdShop()
    {
        return $this->id_shop;
    }

    /**
     * Return web path of module
     *
     * @return string Web path
     */
    public function getWebPath()
    {
        return $this->_path;
    }

    /**
     * Check module status
     * 
     * @param string $module_name
     * @return bool or string False or Warning
     */
    protected function checkModuleStatus($module_name)
    {
        if (!Module::isInstalled($module_name)) {
            return sprintf($this->l('Module "%s" is not installed'), $module_name);
        } elseif (Module::getInstanceByName($module_name)->active == 0) {
            return sprintf($this->l('Module "%s" is not active'), $module_name);
        }
        return false;
    }

    /**
     * Check for new layouts
     * 
     * @param int $id_hook
     * @param array $old_layouts
     * @return array New layouts
     */
    public function checkNewLayouts($id_hook, $old_layouts)
    {
        $layouts = TMMegaLayoutLayouts::getLayoutsForHook($id_hook, $this->id_shop);
        $new_layouts = array();
        if ($layouts) {
            foreach ($layouts as $layout) {
                $new = false;
                if ($old_layouts) {
                    foreach ($old_layouts as $old_layout) {
                        if ($old_layout['id_layout'] == $layout['id_layout']) {
                            $new = false;
                            break;
                        } else {
                            $new = true;
                        }
                    }
                } else {
                    return $layouts;
                }
                if ($new) {
                    $new_layouts[] = $layout;
                }
            }
        }
        return $new_layouts;
    }

    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name || Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJquery();
            $this->context->controller->addJqueryUI('ui.sortable');
            $this->context->controller->addJqueryPlugin('colorpicker');
            $this->context->controller->addJS($this->_path . 'views/js/tmmegalayout_admin.js');
            $this->context->controller->addCSS($this->_path . 'views/css/tmmegalayout_admin.css');
        }
    }

    /**
     * @param int $id_unique
     * @return html Form with item styles
     */
    public function getItemStyles($id_unique)
    {
        $this->context->smarty->assign('id_unique', $id_unique);

        if ($this->checkUniqueStylesExists($id_unique)) {
            if ($content = $this->getStylesContent($id_unique)) {
                $styles = $this->encodeStyles($content);
                $this->context->smarty->assign('styles', $styles);
            }
        }

        return $this->display($this->_path, 'views/templates/admin/tools/styles.tpl');
    }

    /**
     * @param int $id_unique
     * @return bool||string image url
     */
    public function getItemImageUrl($id_unique)
    {
        if ($this->checkUniqueStylesExists($id_unique)) {
            if ($content = $this->getStylesContent($id_unique)) {
                $bgImage = $this->encodeStyles($content);
                if (!isset($bgImage['background_image'])) {
                    return false;
                }

                return $bgImage['background_image'];
            }
        }

        return false;
    }

    /**
     * @param int $id_unique
     * @param string $style_path Path to styles
     * @return bool Styles exist
     */
    public function checkUniqueStylesExists($id_unique, $style_path = null)
    {
        if ($style_path == null) {
            $style_path = $this->style_path;
        }

        if (!file_exists($style_path . $id_unique . '.css')) {
            return false;
        }

        return true;
    }

    /**
     * @param int $id_unique
     * @param string $style_path
     * @return bool|string False or Styles
     */
    public function getStylesContent($id_unique, $style_path = null)
    {
        if ($style_path == null) {
            $style_path = $this->style_path;
        }
        if (!$content = Tools::file_get_contents($style_path . $id_unique . '.css')) {
            return false;
        }

        return $content;
    }

    /**
     * @param string $styles
     * @return array Array of styles
     */
    public function encodeStyles($styles)
    {
        $styles_content = $this->getStyleContent($styles);
        
        return $this->convertToStylesArray($styles_content);
    }

    /**
     * @param string $styles
     * @return string Item styles
     */
    protected function getStyleContent($styles)
    {
        $content = explode('{', str_replace('}', '', $styles));

        return trim($content[1]);
    }

    /**
     * @param string $data Styles
     * @return array Styles array
     */
    protected function convertToStylesArray($data)
    {
        $styles = array();
        $rows = explode(';', trim($data));

        foreach ($rows as $row) {
            $row = explode(':', $row);

            if ($row[0] && $row[1]) {
                $styles[str_replace('-', '_', trim($row[0]))] = trim($row[1]);
            }
        }

        return $styles;
    }

    /**
     * @param int $id_unique
     * @param string $styles
     * @param string $style_path Path to styles
     * @param bool $import Save mode
     * @return bool True if file saved
     */
    public function saveItemStyles($id_unique, $styles, $style_path = null, $import = false)
    {
        if ($style_path == null) {
            $style_path = $this->style_path;
        }
        if ($id_unique && $styles) {
            $content = $this->generateItemStyles($id_unique, $styles);
            $file = fopen($style_path . $id_unique . '.css', 'w');
            fwrite($file, $content);
            fclose($file);
            if (!$import) {
                $this->combineAllItemsStyles();
            }

            return true;
        }
    }

    /**
     * @param int $id_unique
     * @param string $styles string
     * @return string Styles
     */
    protected function generateItemStyles($id_unique, $styles)
    {
        $style = '';
        $style .= '.' . $id_unique . ' {';

        foreach ($styles as $key => $value) {
            if ($key && $value) {
                $key = str_replace('_', '-', $key);
                $style .= $key . ':' . $value . ';';
            }
        }

        $style .= '}';

        return $style;
    }

    /**
     * Delete item styles
     * @param int $id_unique
     * @return bool True if styles deleted
     */
    public function deleteItemStyles($id_unique)
    {
        $res = true;

        if ($this->checkUniqueStylesExists($id_unique)) {
            $res &= @unlink($this->style_path . $id_unique . '.css');
            $res &= $this->combineAllItemsStyles();
        }

        return $res;
    }

    /**
     * Combinate all active styles to main file
     *
     * @return bool True if styles combinated
     */
    public function combineAllItemsStyles()
    {
        $dir_files = Tools::scandir($this->style_path, 'css');
        $active_files = TMMegaLayoutItems::getShopItemsStyles();
        $combined_css = '';

        foreach ($dir_files as $dir_file) {
            if ($active_files) {
                if (file_exists($this->style_path . $dir_file) && in_array(str_replace('.css', '', $dir_file), $active_files)) {
                    $combined_css .= Tools::file_get_contents($this->style_path . $dir_file) . "\n";
                }
            }
        }

        if (!Tools::isEmpty($combined_css)) {
            // combine all custom style to one css file
            $file = fopen($this->style_path . 'combined_unique_styles_' . $this->context->shop->id . '.css', 'w');
            fwrite($file, $combined_css);
            fclose($file);
        } else {
            // remove cobined css file if no custom style exists
            if (file_exists($this->style_path . 'combined_unique_styles_' . $this->context->shop->id . '.css')) {
                @unlink($this->style_path . 'combined_unique_styles_' . $this->context->shop->id . '.css');
            }
        }

        return true;
    }

    /**
     * Copy from $src to $dst
     * @param string $src Folder
     * @param string $dst Folder
     */
    public static function recurseCopy($src, $dst)
    {
        @mkdir($dst);

        if (file_exists($src)) {
            $files = scandir($src);
            foreach ($files as $file) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    if (is_dir($src . '/' . $file)) {
                        Tmmegalayout::recurseCopy($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
        }
    }

    /**
     * Get module icon if exists
     * @return bool|string path to image or false
     */
    public static function getModuleIcon($module_name)
    {
        if (file_exists(_PS_MODULE_DIR_.$module_name.'/logo.png')) {
            $image = _MODULE_DIR_.$module_name.'/logo.png';
        } elseif (file_exists(_PS_MODULE_DIR_.$module_name.'/logo.gif')) {
            $image = _MODULE_DIR_.$module_name.'/logo.gif';
        } else {
            $image = false;
        }

        return $image;
    }

    /**
     * @return int|string Max file size to upload
     */
    public static function getMaxFileSize()
    {
        $max_file_size = ini_get('post_max_size');
        $result = trim($max_file_size);
        $last = Tools::strtolower($result);

        switch ($last) {
            case 'g':
                $result *= 1024;
                break;
            case 'm':
                $result *= 1024;
                break;
            case 'k':
                $result *= 1024;
                break;
        }

        return $result;
    }

    /**
     * Check permission on file, and rewrite it
     *
     * @param string $file Path to file
     * @param string $new_perms New permissions
     * @return bool
     */
    public static function checkPerms($file, $new_perms)
    {
        $perms = fileperms($file);

        $info = '';
        // Owner
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ?
                        (($perms & 0x0800) ? 's' : 'x' ) :
                        (($perms & 0x0800) ? 'S' : '-'));

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ?
                        (($perms & 0x0400) ? 's' : 'x' ) :
                        (($perms & 0x0400) ? 'S' : '-'));

        // World
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ?
                        (($perms & 0x0200) ? 't' : 'x' ) :
                        (($perms & 0x0200) ? 'T' : '-'));

        if ($info != 'rwxrwxrwx') {
            chmod($file, $new_perms);
        }

        return true;
    }

    /**
     * Render layout form
     * 
     * @param int $id_hook
     * @return string Html of form
     */
    public function addLayoutForm($id_hook)
    {
        $this->context->smarty->assign('id_hook', $id_hook);

        return $this->display(__FILE__, 'views/templates/admin/tmmegalayout_add-layout.tpl');
    }

    /**
     * Render module form
     *
     * @param int $id_hook
     * @param int $id_layout
     * @return string
     */
    public function addModuleForm($id_hook, $id_layout)
    {
        $this->context->smarty->assign('modules_list', $this->getHookModulesList($id_hook, $id_layout));
        $this->context->smarty->assign('id_hook', $id_hook);

        return $this->display($this->_path, 'views/templates/admin/tools/modules-select.tpl');
    }

    /**
     * @param int $id_hook
     * @param string $layout_name
     * @return bool|int If layout added return id
     */
    public function addLayout($id_hook, $layout_name)
    {
        $layout = new TMMegaLayoutLayouts();
        $layout->id_hook = $id_hook;
        $layout->layout_name = $layout_name;
        $layout->id_shop = $this->id_shop;

        if (!$layout->save()) {
            return false;
        }

        return $layout->id;
    }

    /**
     * Render message
     *
     * @param int $id_layout
     * @param string $action
     * @param string $text
     * @return string Html of message
     */
    public function showMessage($id_layout, $action, $text = '')
    {
        $this->context->smarty->assign('message', array(
            'type' => $action,
            'id_layout' => $id_layout,
            'text' => $text
        ));

        return $this->display($this->_path, 'views/templates/admin/tools/messages.tpl');
    }

    public function hookActionObjectShopAddAfter($params)
    {
        return $params;
    }

    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/tmmegalayout.js');
        $this->context->controller->addCSS($this->_path . '/views/css/tmmegalayout.css');
        $this->context->controller->addCSS($this->_path . '/views/css/items/combined_unique_styles_' . $this->context->shop->id . '.css');
    }

    public function hookTmMegaLayoutTopColumn()
    {
        $this->context->smarty->assign('isMegaTopColumn', true);
        return $this->getLayoutFront('displayTopColumn');
    }

    public function hookTmMegaLayoutHome()
    {
        $this->context->smarty->assign('isMegaHome', true);
        return $this->getLayoutFront('displayHome');
    }

    public function hookTmMegaLayoutFooter()
    {
        $this->context->smarty->assign('isMegaFooter', true);
        return $this->getLayoutFront('displayFooter');
    }
}
