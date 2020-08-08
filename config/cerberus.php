<?php
// Error reporting disabled, uncomment for debugging
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

Configure::set('cerberus.ticket.color.open', '#FFFFFF');
Configure::set('cerberus.ticket.color.waiting', '#FFDFDF');
Configure::set('cerberus.ticket.color.closed', '#DDFFDD');
Configure::set('cerberus.ticket.color.deleted', '#EAEAEA');

Configure::set('cerberus.custom.fieldset.name.org',  'blesta');
Configure::set('cerberus.custom.field.name.org.id',  'id');
Configure::set('cerberus.custom.field.name.org.url', 'url');

// TODO FIXME: update to correct naming and format
Configure::set('cerberus.tktCustomFieldName.client_url', 'client_url');
Configure::set('cerberus.tktCustomFieldName.service_url', 'service_url');

Configure::set('cerberus.message_date_format', 'F jS, Y \a\t h:i A');

// A list of bucket group ids where clients are not able to respond
// to tickets. Useful for moving tickets to an archive group or
// havings tickets used as logs for services, actions, etc.

// An empty array means allow all (default)
Configure::set('cerberus.readonly.groups.ids', []);
