{**
 * fbvTestForm.tpl
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * The fbv-coded test form.
 *
 *}

<form>

<h2>#1.1 Text input sizes</h2>

{fbvFormArea id="test1-1"}
	{fbvFormSection title=""}
	<p>Small:</p>
	{fbvElement type="text" id="password" size=$fbvStyles.size.SMALL}
	{/fbvFormSection}

	{fbvFormSection title=""}
	<p>Medium:</p>
	{fbvElement type="text" id="password" size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	{fbvFormSection title=""}
	<p>Large:</p>
	{fbvElement type="text" id="password" size=$fbvStyles.size.LARGE}
	{/fbvFormSection}

	{fbvFormSection title=""}
	<p>Default: (same size as Large)</p>
	{fbvElement type="text" id="password"}
	{/fbvFormSection}
{/fbvFormArea}

<h2>#1.2 Text area sizes</h2>

{fbvFormArea id="test1-2"}
	{fbvFormSection title=""}
	<p>Small:</p>
	{fbvElement type="textarea" id="password" size=$fbvStyles.size.SMALL}
	{/fbvFormSection}

	{fbvFormSection title=""}
	<p>Medium:</p>
	{fbvElement type="textarea" id="password" size=$fbvStyles.size.SMALL}
	{/fbvFormSection}

	{fbvFormSection title=""}
	<p>Large:</p>
	{fbvElement type="textarea" id="password" size=$fbvStyles.size.SMALL}
	{/fbvFormSection}
{/fbvFormArea}

<h2>#2.1 Row Continuity</h2>
<p><strong>Expected result</strong>: <em>input elements in a continuous row that is not segmented by blank spaces</em></p>
{fbvFormArea id="test2-1"}
	{fbvFormSection title=""}
	{fbvElement type="text" id="password" size=$fbvStyles.size.SMALL}
	{fbvElement type="text" id="password" size=$fbvStyles.size.MEDIUM}
	{fbvElement type="text" id="password" size=$fbvStyles.size.LARGE}
	{/fbvFormSection}
{/fbvFormArea}


{fbvFormArea id="test2-2"}
	{fbvFormSection title=""}
	<p>No column layout specified. Continuous row.</p>
	{fbvElement type="radio" id="password" label="en_US"}
	{fbvElement type="radio" id="password" label="fr_CA"}
	{fbvElement type="radio" id="password" label="te_ST"}
	{/fbvFormSection}

	{fbvFormSection title="" layout=$fbvStyles.layout.TWO_COLUMNS}
	<p>Two columns</p>

	{fbvElement type="checkbox" id="password" label="en_US"}
	{fbvElement type="checkbox" id="password" label="fr_CA"}
	{fbvElement type="checkbox" id="password" label="te_ST1"}
	{fbvElement type="checkbox" id="password" label="te_ST2"}
	{/fbvFormSection}

	{fbvFormSection title="" layout=$fbvStyles.layout.THREE_COLUMNS}
	<p>Three columns</p>
	{fbvElement type="checkbox" id="password" label="en_US"}
	{fbvElement type="checkbox" id="password" label="fr_CA"}
	{fbvElement type="checkbox" id="password" label="te_ST"}
	{/fbvFormSection}

{/fbvFormArea}

</form>
