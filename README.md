# Template [![Build Status](https://travis-ci.org/crazedsanity/template.svg?branch=master)](https://travis-ci.org/crazedsanity/template)
Template system, based on cs_genericPage from cs-content (v1.x).

This is a templating engine, built primarily to allow easy separation between PHP and HTML.  Separating PHP from HTML helps keep the code clean and tends to avoid spaghetti code from happening.

## Quick Definitions

*Template*: a file containing template vars.

*Template Var*: a string of text, following standard variable naming conventions, wrapped in curly braces: ```{templateVar}```

## Sample

This is a simple example.  Keep in mind that there's a lot of different ways to accomplish this same end result.

```php
$recordSet = array(
			0 => array(
				'primary_id'    => 1,
				'record_name'   => 'The First Record',
				'another_field' => 'field value',
				'is_active'     => 0,
			),
			1 => array(
				'primary_id'    => 3,
				'record_name'   => 'A third record',
				'another_field' => 'something else',
				'is_active'     => 1,
			),
);
$tmpl = new Template(__DIR__ .'/path/to/file.tmpl');
$output = $tmpl->renderRows($recordSet);
```

The associated template file would look something like this:
```
===============
{primary_id}|{record_name}|{another_field}|{is_active}|{invalid_field}
----
```

The output would look like this:

```
===============
1|The First Record|field value|0|
----
===============
3|A third record|something else|1|
----
```
