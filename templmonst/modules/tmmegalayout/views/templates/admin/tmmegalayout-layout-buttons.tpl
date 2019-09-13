{**
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
*  @copyright 2002-2016 TemplateMonster
*  @license   http://opensource.org/licenses/GPL-2.0 General Public License (GPL 2.0)
*}

{if isset($content.layout)}
    <p {if isset($content.layout)}data-layout-id="{$content.id_layout|escape:'htmlall':'UTF-8'}"{/if} class="tmlist-layout-btns clearfix">
        <a data-layout-id="{$content.id_layout|escape:'htmlall':'UTF-8'}" href="#" class="{if $content.status}hidden{/if} layout-btn use-layout"><i class="process-icon-toggle-off"></i> {l s='Use as default' mod='tmmegalayout'}</a>
        <a data-layout-id="{$content.id_layout|escape:'htmlall':'UTF-8'}" href="#" class="{if !$content.status}hidden{/if} layout-btn disable-layout"><i class="process-icon-toggle-on"></i> {l s='Use as default' mod='tmmegalayout'}</a>
    </p>
{/if}