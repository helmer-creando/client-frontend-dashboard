# Polylang API Reference (v3.7.x)

> Sourced from [Polylang Function Reference](https://polylang.pro/documentation/support/developers/function-reference/)
> and [Polylang WordPress.com Docs](https://polylang.wordpress.com/documentation/documentation-for-developers/functions-reference/).
>
> **Critical rule:** Always check `function_exists()` before calling any Polylang function.
> Polylang deletes the plugin folder on update — unguarded calls cause fatal errors.

---

## Functions Used by CFD Polylang

### `pll_current_language( $value = 'slug' )`

Returns the current language.

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `$value` | `string` | `'slug'` | What to return: `'slug'` (2-letter code), `'locale'` (e.g. `en_US`), or `'name'` (full name) |

**Returns:** `string|false` — The requested language property, or `false` if no language is set.

```php
$lang = pll_current_language();        // 'es'
$lang = pll_current_language('name');   // 'Español'
$lang = pll_current_language('locale'); // 'es_ES'
```

---

### `pll_default_language( $value = 'slug' )`

Returns the default language.

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `$value` | `string` | `'slug'` | Same options as `pll_current_language()` |

**Returns:** `string|false`

---

### `pll_get_post( $post_id, $slug = '' )`

Returns the translation of a post (or page, or CPT) in the given language.

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `$post_id` | `int` | — | The post ID |
| `$slug` | `string` | `''` | Language slug. Empty = current language |

**Returns:** `int|false|null` — Translated post ID, `false` if no translation exists, `null` if the language doesn't exist.

```php
$en_id = pll_get_post( 42, 'en' ); // Get English version of post 42
```

---

### `pll_get_post_translations( $post_id )`

Returns all translations of a post as an associative array.

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `$post_id` | `int` | — | The post ID |

**Returns:** `array` — Associative array: `[ 'lang_slug' => post_id, ... ]`

```php
$translations = pll_get_post_translations( 42 );
// [ 'es' => 42, 'en' => 58, 'fr' => 73 ]
```

---

### `pll_get_post_language( $post_id, $field = 'slug' )`

Gets the language of a post.

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `$post_id` | `int` | — | The post ID |
| `$field` | `string` | `'slug'` | `'slug'`, `'locale'`, or `'name'` |

**Returns:** `string|false`

```php
$lang = pll_get_post_language( 42 );        // 'es'
$lang = pll_get_post_language( 42, 'name' ); // 'Español'
```

---

### `pll_the_languages( $args = array() )`

Displays or returns a language switcher.

| Param (in `$args`) | Type | Default | Description |
|---------------------|------|---------|-------------|
| `'dropdown'` | `int` | `0` | `1` = render as `<select>` |
| `'show_names'` | `int` | `1` | `1` = show language name |
| `'show_flags'` | `int` | `0` | `1` = show flag images |
| `'hide_empty'` | `int` | `1` | `1` = hide languages with no content |
| `'post_id'` | `int` | — | Show links to this post's translations |
| `'raw'` | `int` | `0` | `1` = return raw array instead of HTML |
| `'echo'` | `int` | `1` | `0` = return string instead of echoing |

**Returns:** `string|array|void` — HTML string, raw array, or echoed output.

When `'raw' => 1`, returns array of objects with properties:
- `slug`, `name`, `locale`, `flag` (HTML img), `current_lang` (bool), `url`, `id` (term_id)

```php
$languages = pll_the_languages( [ 'raw' => 1 ] );
foreach ( $languages as $lang ) {
    echo $lang['slug'];  // 'en'
    echo $lang['name'];  // 'English'
    echo $lang['flag'];  // '<img src="..." />'
    echo $lang['url'];   // language-specific URL
}
```

---

### `pll_languages_list( $args = array() )`

Returns a flat list of language slugs (or other fields).

| Param (in `$args`) | Type | Default | Description |
|---------------------|------|---------|-------------|
| `'hide_empty'` | `int` | `0` | `1` = hide languages with no content |
| `'fields'` | `string` | `'slug'` | `'slug'`, `'locale'`, or `'name'` |

**Returns:** `array` — e.g. `[ 'es', 'en', 'fr' ]`

---

## WP_Query Language Filtering

Polylang automatically filters `WP_Query` to the current language on the frontend. To explicitly control language:

```php
// Query posts in a specific language
$args = [
    'post_type' => 'retreat',
    'lang'      => 'es',       // Polylang intercepts this param
];
$query = new WP_Query( $args );

// Query multiple languages
$args['lang'] = 'es,en';

// Disable language filter (get ALL languages)
$args['lang'] = '';
```

The `lang` parameter also works with `get_posts()` and `get_terms()`.

---

## Safety Pattern

```php
if ( function_exists( 'pll_current_language' ) ) {
    $lang = pll_current_language();
}
```

All CFD Polylang code must guard every Polylang function call, or (preferred) check once at bootstrap and bail early if Polylang is not active.
