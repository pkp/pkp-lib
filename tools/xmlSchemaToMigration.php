<?php

/**
 * @file tools/xmlSchemaToMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class xmlSchemaToMigration
 * @ingroup tools
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class xmlSchemaToMigration extends CommandLineTool {
	/** @var string Name of source file/directory */
	protected $source;

	/** @var string Name of the generated PHP class */
	protected $className;

	/**
	 * Constructor
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		array_shift($argv); // Shift the tool name off the top

		$this->source = array_shift($argv);
		$this->className = array_shift($argv);

		// The source file/directory must be specified and exist.
		if (empty($this->source) || !file_exists($this->source)) {
			$this->usage();
			exit(2);
		}

		if (empty($this->className) | !preg_match('/^[a-zA-Z]+$/', $this->className)) {
			$this->usage();
			exit(3);
		}
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Script to convert ADODB XMLSchema-based schema descriptors to Illuminate migrations\n\n"
			. "Usage: {$this->scriptName} input-schema-file.xml GenerateClassNamed\n\n";
	}

	/**
	 * Convert XML locale content to PO format.
	 */
	function execute() {
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$doc->loadXML(file_get_contents($this->source));
		if ($doc->documentElement->nodeName != 'schema') throw new Exception('Invalid document element ' . $this->documentElement->nodeName);

		echo "<?php

/**
 * @file classes/migration/$this->className.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class $this->className
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class $this->className extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {\n";
		foreach ($doc->documentElement->childNodes as $tableNode) {
			if ($tableNode->nodeType == XML_COMMENT_NODE) continue; // Skip comments
			if ($tableNode->nodeName != 'table') throw new Exception('Unexpected table node name ' . $tableNode->nodeName);
			foreach ($tableNode->childNodes as $tableChild) {
				if ($tableChild->nodeName == 'descr') echo "\t\t// " . $tableChild->nodeValue . "\n";
			}
			echo "\t\tCapsule::schema()->create('" . $tableNode->getAttribute('name') . "', function (Blueprint \$table) {\n";
			$keys = [];
			$hasAutoIncrementNamed = null;
			foreach ($tableNode->childNodes as $tableChild) switch (true) {
				case $tableChild->nodeType == XML_COMMENT_NODE: throw new Exception('Unexpected comment in table ' . $tableNode->getAttribute('name') . '!');
				case $tableChild->nodeName == 'field':
					// Preprocess comments
					foreach ($tableChild->childNodes as $fieldChild) switch (true) {
						case $fieldChild->nodeType == XML_COMMENT_NODE:
							echo "\t\t\t// " . $fieldChild->nodeValue . "\n";
							break;
					}
					echo "\t\t\t";
					$allowDefault = true;
					switch ($tableChild->getAttribute('type')) {
						case 'XL':
							echo "\$table->longText('" . $tableChild->getAttribute('name') . "')";
							$allowDefault = false;
							break;
						case 'X':
							echo "\$table->text('" . $tableChild->getAttribute('name') . "')";
							$allowDefault = false;
							break;
						case 'I1':
							// This may be a boolean or a numeric constant!
							// MySQL complains (https://github.com/laravel/framework/issues/8840)
							// but it's better to review and fix manually rather than rely on this.
							echo "\$table->tinyInteger('" . $tableChild->getAttribute('name') . "')";
							break;
						case 'I2':
							echo "\$table->smallInteger('" . $tableChild->getAttribute('name') . "')";
							break;
						case 'I4':
							echo "\$table->integer('" . $tableChild->getAttribute('name') . "')";
							break;
						case 'I8':
							echo "\$table->bigInteger('" . $tableChild->getAttribute('name') . "')";
							break;
						case 'F':
							echo "\$table->float('" . $tableChild->getAttribute('name') . "', 8, 2)";
							break;
						case 'T':
							echo "\$table->datetime('" . $tableChild->getAttribute('name') . "')";
							break;
						case 'D':
							echo "\$table->date('" . $tableChild->getAttribute('name') . "')";
							break;
						case 'C':
							echo "\$table->string('" . $tableChild->getAttribute('name') . "', " . (int) $tableChild->getAttribute('size') . ")";
							break;
						case 'C2':
							echo "\$table->string('" . $tableChild->getAttribute('name') . "', " . (int) $tableChild->getAttribute('size') . ")";
							break;
						default: throw new Exception('Unspecified or unknown table type ' . $tableChild->getAttribute('type') . ' in column ' . $tableChild->getAttribute('name') . ' of table ' . $tableNode->getAttribute('name'));
					}
					$nullable = true;
					$autoIncrement = false;
					foreach ($tableChild->childNodes as $fieldChild) switch (true) {
						case $fieldChild->nodeType == XML_COMMENT_NODE: break; // Already processed above
						case $fieldChild->nodeName == 'NOTNULL': $nullable = false; break;
						case $fieldChild->nodeName == 'AUTOINCREMENT':
							$autoIncrement = true;
							$hasAutoIncrementNamed = $tableChild->getAttribute('name');
							$nullable = false;
							break;
						case $fieldChild->nodeName == 'KEY': $keys[] = $tableChild->getAttribute('name'); break;
						case $fieldChild->nodeName == 'DEFAULT':
							if ($allowDefault) {
								$value = $fieldChild->getAttribute('VALUE');
								if (is_string($value) && ctype_digit($value)) $value = (int) $value;
								echo "->default(" . var_export($value, true) . ")";
							}
							break;
						case $fieldChild->nodeName == 'descr': echo "->comment(" . var_export($fieldChild->nodeValue, true) . ")"; break;
						case $fieldChild->nodeType == XML_TEXT_NODE:
							if (trim($fieldChild->nodeValue) !== '') throw new Exception('Unexpected content in field node!');
							break;
						default: throw new Exception('Unhandled child node (type ' . $fieldChild->nodeType . ') to column ' . $tableChild->getAttribute('name') . ' of table ' . $tableNode->getAttribute('name'));
					}
					if ($autoIncrement) echo "->autoIncrement()";
					if ($nullable && !in_array($tableChild->getAttribute('name'), $keys)) echo "->nullable()";
					echo ";\n";
					break;
				case $tableChild->nodeName == 'index':
					if (!$tableChild->hasAttribute('name')) throw new Exception('Unnamed index on table ' . $tableNode->getAttribute('name'));
					$indexType = 'index';
					$columns = [];
					foreach ($tableChild->childNodes as $indexChild) switch (true) {
						case $indexChild->nodeType == XML_COMMENT_NODE:
							echo "\t\t\t// " . $indexChild->nodeValue . "\n";
							break;
						case $indexChild->nodeName == 'UNIQUE': $indexType = 'unique'; break;
						case $indexChild->nodeName == 'col': $columns[] = trim($indexChild->nodeValue);
							break;
						default: throw new Exception('Unhandled index node child ' . $indexChild->nodeName);
					}
					if (empty($columns)) throw new Exception('Empty column list for index on table ' . $tableNode->getAttribute('name') . '))!');
					echo "\t\t\t\$table->$indexType(['" . implode("', '", $columns) . "'], '" . $tableChild->getAttribute('name') . "');\n";
					break;
				case $tableChild->nodeName == 'descr': break; // Handled above.
					break;
				default: throw new Exception('Don\'t know how to handle this table child node (' . $tableChild->nodeName . '))!');
			}
			echo "\t\t});\n\n";
			if (count($keys)>1 && $hasAutoIncrementNamed !== null) {
				echo "\t\t// Work-around for compound primary key\n";
				echo "\t\tswitch (Capsule::connection()->getDriverName()) {\n";
				echo "\t\t\tcase 'mysql': Capsule::connection()->unprepared(\"ALTER TABLE " . $tableNode->getAttribute('name') . " DROP PRIMARY KEY, ADD PRIMARY KEY (" . implode(", ", $keys) . ")\"); break;\n";
				echo "\t\t\tcase 'pgsql': Capsule::connection()->unprepared(\"ALTER TABLE " . $tableNode->getAttribute('name') . " DROP CONSTRAINT " . $tableNode->getAttribute('name') . "_pkey; ALTER TABLE " . $tableNode->getAttribute('name') . " ADD PRIMARY KEY (" . implode(", ", $keys) . ");\"); break;\n";
				echo "\t\t}\n";
			} elseif (count($keys)==1 && $hasAutoIncrementNamed !== null) {
				// Handled well by autoIncrement
			} elseif ($hasAutoIncrementNamed === null) {
				// No autoincrement specified; we're OK without further consideration
			} elseif (count($keys)==0) {
				// No primary keys specified; we're OK without further consideration
			} else {
				throw new Exception('Not sure how to handle primary key setup for table ' . $tableNode->getAttribute('name') . '.');
			}
		}
		echo "\t}\n}";
	}
}

$tool = new xmlSchemaToMigration(isset($argv) ? $argv : array());
$tool->execute();

