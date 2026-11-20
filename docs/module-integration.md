# Supplying MCP context from a module

A module can hand mcpapi field and handler descriptions, and relations between
handlers, so MCP clients see more than raw IPF schema when they call
`describe_handler` or `list_modules`. This needs no dependency on mcpapi: the
calls below do nothing when mcpapi is not installed or not active.

mcpapi never includes or calls your module's code on its own. The only thing
it ever does with the file below is check, with `is_file()`, whether it
exists - during mcpapi's own install or update - to tell the webmaster which
installed modules could supply MCP context if they are updated. Your module's
own install, update and uninstall routines are the only place this file is
ever executed, same as any other optional integration you already write for
this site (compare `include/comment.inc.php`).

## 1. Ship the convention file

Create `include/mcpapi.inc.php` in your module, defining one function that
returns your context entries:

```php
<?php

defined('ICMS_ROOT_PATH') or die('ICMS root path not defined');

function imforum_mcpapi_context(): array
{
    return [
        [
            'scope' => 'handler',
            'handler' => 'post',
            'description' => 'A single message in a forum thread.',
        ],
        [
            'scope' => 'field',
            'handler' => 'post',
            'field' => 'topic_id',
            'description' => 'The thread this post belongs to.',
            'relation_module' => 'imforum',
            'relation_handler' => 'topic',
        ],
    ];
}
```

Each entry has a `scope` of `module`, `handler` or `field`, an optional
`handler`/`field`, a `description`, and - only meaningful at `scope: field` -
an optional `relation_module`/`relation_handler` pointing at the handler the
field's value refers to. Malformed entries are skipped by mcpapi; they never
cause your module's install or update to fail.

## 2. Push on install and update

In your module's own `include/oninstall.inc.php` and `include/onupdate.inc.php`:

```php
require_once __DIR__ . '/mcpapi.inc.php';

function imforum_mcpapi_push(): void
{
    $context = icms_getModuleHandler('context', 'mcpapi', null, true);

    if (! $context instanceof icms_ipf_Handler) {
        return;
    }

    $context->registerForModule('imforum', imforum_mcpapi_context());
}

function icms_module_install_imforum($module): bool
{
    // ... your existing install logic ...

    imforum_mcpapi_push();

    return true;
}
```

`registerForModule()` replaces every entry your module previously pushed; it
never touches context an administrator added through mcpapi's own admin
pages.

## 3. Clean up on uninstall

In `include/onuninstall.inc.php`:

```php
function icms_module_uninstall_imforum($module): bool
{
    $context = icms_getModuleHandler('context', 'mcpapi', null, true);

    if ($context instanceof icms_ipf_Handler) {
        $context->deleteForModule('imforum');
    }

    // ... your existing uninstall logic ...

    return true;
}
```

## Skills are not pushed by modules

Skills (named, free-text procedures for a site-specific task) are authored
only by a site administrator in mcpapi's own admin pages, since they often
encode decisions specific to one site or spanning several modules. There is
no push mechanism for them, and nothing for a module to ship.

## Using context and skills as a site administrator

Everything below is added through mcpapi's own admin pages
(`admin/context.php` and `admin/skill.php`), not by editing a module. It
needs no dependency from the described module and is never touched by that
module's own install or update.

### Context

Add context at the **module**, **handler**, or **field** level:

| Scope     | When to use it                      | Example                                                               |
|-----------|--------------------------------------|------------------------------------------------------------------------|
| `module`  | What this module does overall        | "SimpleCart manages sales campaigns, products and orders."            |
| `handler` | What this kind of object means       | "An `order` is one completed order placed by a customer."             |
| `field`   | What this field means, and what it points to | "`campaign_id` points at the campaign" plus a relation            |

`relation_module`/`relation_handler` only make sense at `scope: field`, and
only when the field is an ID referring to another handler's objects (a
foreign key). This is the highest-value addition: without it, an MCP client
has no way to know that `campaign_id: 7` is a concrete campaign record it
should look up first.

**Example - module-level context for `simplecart`** (`scope: module`,
handler/field left empty):

```
Module:       simplecart
Scope:        module
Description:  Sales module for the parents' association: campaigns (e.g. a
              waffle sale), the products in them, and the orders customers
              place.
```

**Example - handler-level context for `simplecart`'s `order` handler**
(`scope: handler`, field left empty):

```
Module:       simplecart
Scope:        handler
Handler:      order
Description:  One completed order placed by a customer within a campaign.
              Does not hold the line items itself - those live in orderitem.
```

**Example - field-level context with a relation**, for `simplecart`'s
`orderitem` handler:

```
Module:          simplecart
Scope:           field
Handler:         orderitem
Field:           order_id
Description:     The order this line item belongs to.
Relation module:  simplecart
Relation handler: order
```

```
Module:          simplecart
Scope:           field
Handler:         question
Field:           campaign_id
Description:     The campaign this question is asked for.
Relation module:  simplecart
Relation handler: campaign
```

`orderitem` does not actually store a `product_id` - at order time, the
chosen product's name and price are copied onto the order item as plain
text/numbers (`product_name`, `product_price`), so later changes to the
product never alter an existing order. The only real relation on
`orderitem` is `order_id`, above.

Together with the `campaign_id` → `campaign` relation on `product`, an MCP
client can now follow the real chain - campaign → product, and
order → order item - instead of guessing what an ID means or assuming a
link that the schema doesn't actually have.

### Skills

Skills are not field descriptions - they are **procedures**: free text
explaining how to correctly do something in several steps, especially when
that combines rules that live in no single field (e.g. "a campaign must be
active before you add products to it").

Fields: `name` (a technical key, no spaces - this is what later shows up as
the prompt name), `title` (a human-readable title), `instructions` (the full
text), `dirname` (optional - leave empty to make it visible to every caller
with MCP access, or set it to restrict it to callers who can read that
module).

**Example 1 - a skill scoped to one module:**

```
Name:         add_product_to_campaign
Title:        Adding a product to a campaign
Module:       simplecart
Instructions: To add a new product to an existing campaign:
              1) look up the campaign with list_objects on simplecart/campaign
                 and confirm active=1;
              2) create the product with create_object on simplecart/product,
                 with campaign_id set to that campaign's id;
              3) verify with get_object that the product is linked to the
                 right campaign.
              Never create a product without a valid, active campaign_id.
```

**Example 2 - a site-specific business rule no single module could know:**

```
Name:         place_order_for_customer
Title:        Placing an order for a customer
Module:       (leave empty - this rule is specific to this site)
Instructions: When placing an order through the MCP tools:
              1) create the order with create_object on simplecart/order;
              2) for each chosen product, add an orderitem with create_object,
                 setting order_id to that order's id, and copying the
                 product's current name and price into product_name and
                 product_price (orderitem has no product_id - it stores a
                 snapshot so later product edits never change past orders);
              3) on this site nobody pays through the shop itself - always
                 point the customer to the bank transfer instructions in the
                 confirmation email, and never mark an order as "paid".
```

That second example is exactly why skills exist independently of modules:
"how payment actually works here" appears nowhere in simplecart's own code,
yet it is essential to handling an order correctly on this site.
