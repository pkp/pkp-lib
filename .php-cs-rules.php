<?php

return [
		'array_indentation' => true,
		'binary_operator_spaces' => true,
		'concat_space' => ['spacing' => 'one'],
		'explicit_string_variable' => true,
		'method_chaining_indentation' => true,
		'no_extra_blank_lines' => [
			'tokens' => ['break', 'case', 'continue', 'curly_brace_block', 'default', 'extra', 'parenthesis_brace_block', 'return', 'square_brace_block', 'switch', 'throw', 'use', 'use_trait'],
		],
		'no_unused_imports' => true,
		'no_spaces_around_offset' => true,
		'no_superfluous_phpdoc_tags' => true,
		'no_whitespace_before_comma_in_array' => true,
		'ordered_imports' => ['sort_algorithm' => 'alpha'],
		'phpdoc_add_missing_param_annotation' => false,
		'phpdoc_no_empty_return' => true,
		'phpdoc_order' => true,
		'phpdoc_separation' => true,
		'phpdoc_var_annotation_correct_order' => true,
		'single_quote' => true,
		'standardize_increment' => true,
		'standardize_not_equals' => true,
		'ternary_to_null_coalescing' => true,
];