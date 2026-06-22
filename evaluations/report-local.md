# Local Joomla MCP Evaluation

## Task 1 — PASS
**Question**: How many tools does this MCP server expose? Use tools/list and return only the integer.
**Expected**: `49`
**Actual**: `49`

## Task 2 — PASS
**Question**: Inspect every tool returned by tools/list. How many tools have readOnlyHint set to true in their annotations? Return only the integer.
**Expected**: `23`
**Actual**: `23`

## Task 3 — PASS
**Question**: Using list_installed_templates with client set to site, consider only enabled templates. Which template element name comes first alphabetically? Return only the element name in lowercase.
**Expected**: `cassiopeia`
**Actual**: `cassiopeia`

## Task 4 — PASS
**Question**: Using list_installed_templates with client set to administrator, is there an installed template whose element is exactly 'atum'? Answer True or False only.
**Expected**: `True`
**Actual**: `True`

## Task 5 — PASS
**Question**: Call list_installed_languages and read meta.application_default. Return only the default language tag.
**Expected**: `en-GB`
**Actual**: `en-GB`

## Task 6 — PASS
**Question**: List site menus and find the menu whose title is exactly 'Main Menu'. Return only its menutype alias.
**Expected**: `mainmenu`
**Actual**: `mainmenu`

## Task 7 — PASS
**Question**: Retrieve article ID 1 with get_article_by_id. Return only its alias.
**Expected**: `home`
**Actual**: `home`

## Task 8 — PASS
**Question**: Among published content languages (published=1), what is the SEF prefix for the language with lang_code en-GB? Return only the SEF value.
**Expected**: `NOT_FOUND`
**Actual**: `NOT_FOUND`

## Task 9 — PASS
**Question**: List site template styles and count how many styles use template element 'cassiopeia'. Return only the integer.
**Expected**: `1`
**Actual**: `1`

## Task 10 — PASS
**Question**: For article ID 1, how many associated items are returned by list_article_associations? Return only the integer.
**Expected**: `0`
**Actual**: `0`

**Accuracy**: 10/10 (100.0%)
