{**
 * controllers/grid/plugins/pluginGallery.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Plugin gallery.
 *}
{url|assign:pluginGalleryGridUrl router=$smarty.const.ROUTE_COMPONENT component="grid.plugins.PluginGalleryGridHandler" op="fetchGrid" escape=false}
{load_url_in_div id="pluginGalleryGridContainer" url=$pluginGalleryGridUrl}
