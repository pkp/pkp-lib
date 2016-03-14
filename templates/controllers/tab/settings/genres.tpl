{**
 * controllers/tab/settings/genres.tpl
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Publication process genres (submission file types).
 *
 *}
<div class="genres">
	{help file="chapter6/workflow/components.md" class="pkp_helpers_align_right"}
	<div class="pkp_helpers_clear"></div>

	{url|assign:genresUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.genre.GenreGridHandler" op="fetchGrid" escape=false}
	{load_url_in_div id="genresContainer" url=$genresUrl}
</div>
