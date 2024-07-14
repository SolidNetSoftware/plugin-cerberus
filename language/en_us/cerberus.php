<?php
// Plugin name
$lang['CerberusPlugin.name'] = 'Cerb';

// Cron
$lang['cerberus.cron.name'] = 'Cerb User Sync Jobs';
$lang['cerberus.cron.desc'] = 'Cron to handle forced / manual user sync jobs for the Cerb plugin';

// Client Area
$lang['cerberus.client.navbar.title']   = 'Helpdesk';
$lang['cerberus.client.widget.title']   = 'Helpdesk Tickets';
$lang['cerberus.client.card.title']     = 'Tickets';

// Admin Area
$lang['cerberus.admin.navbar.title']        = 'Cerb';
$lang['cerberus.admin.navbar.config']       = 'Settings';
$lang['cerberus.admin.navbar.departments']  = 'Departments';
$lang['cerberus.admin.navbar.sync']         = 'User Sync';

// Admin Area Configuration Page
$lang['cerberus.admin.config.api']              = 'Cerb API Configuration';
$lang['cerberus.admin.config.api.save']         = 'Save and Test Connection';
$lang['cerberus.admin.config.api.url']          = 'Base URL (https://example.com/rest/)';
$lang['cerberus.admin.config.api.key']          = 'API Access Key';
$lang['cerberus.admin.config.api.secret']       = 'API Secret Key';
$lang['cerberus.config.ticket.sort']            = 'Sort ticket replies in descending order (newest replies fist)';
$lang['cerberus.config.ticket.attachments']     = 'Allow file attachments in tickets and replies';
$lang['cerberus.admin.config.message.save']     = 'Connected to Cerb Helpdesk running version: %s';
$lang['cerberus.admin.config.error.save']       = 'Failed to connect to Cerb Helpdesk: %s';

// Admin Area User Sync Page
$lang['cerberus.admin.sync.jobs']           = 'Sync Jobs';
$lang['cerberus.admin.sync.created']        = 'Created';
$lang['cerberus.admin.sync.updated']        = 'Updated';
$lang['cerberus.admin.sync.status']         = 'Status';
$lang['cerberus.admin.sync.completed']      = 'Completed';
$lang['cerberus.admin.sync.total']          = 'Total';
$lang['cerberus.admin.sync.button.create']  = 'Create Sync Job';
$lang['cerberus.admin.sync.error.body']     = 'Sync job failed to create - empty post body';
$lang['cerberus.admin.sync.error.job']      = 'Failed to setup cronjob for user sync';
$lang['cerberus.admin.sync.notice.job']     = 'Sync job added to Blesta cron';
$lang['cerberus.admin.sync.info']           = 'User sync is only required for new installation of Blesta <strong>or</strong> Cerb. Once users are synced from Blesta to Cerb, this plugin will automatically update Cerb when users (Clients and Contacts) are updated by clients, staff or any API calls.
<br /><br />
<strong>It is important to configure custom fields in Blesta and Cerb before running user sync</strong>
<br /><br />
This a safe operation: running a sync operation will not remove/destory data in Blesta or Cerb.';

// Cron Job
$lang['cerberus.cron.error.primary_contact'] = 'Failed to sync org because primary contact was not processed';

// Admin Area Departments Page
$lang['cerberus.admin.departments.button.create']           = 'Create Department';
$lang['cerberus.admin.departments.title']                   = 'Cerb Helpdesk';
$lang['cerberus.admin.departments.title.edit']              = 'Edit Department';
$lang['cerberus.admin.departments.button.edit']             = 'Edit';
$lang['cerberus.admin.departments.title.name']              = 'Name';
$lang['cerberus.admin.departments.title.description']       = 'Description';
$lang['cerberus.admin.departments.delete']                  = 'Delete';
$lang['cerberus.admin.departments.delete.confirm']          = 'Are you sure you want to remove %s department? Note: No tickets in cerb will be deleted';
$lang['cerberus.admin.departments.title.group']             = 'Group';
$lang['cerberus.admin.departments.title.bucket']            = 'Bucket';
$lang['cerberus.admin.departments.title.custom_field']      = 'Custom field';
$lang['cerberus.admin.departments.title.default']           = 'Default value';
$lang['cerberus.admin.departments.title.visible']           = 'Visible';
$lang['cerberus.admin.departments.title.required']          = 'Required';
$lang['cerberus.admin.departments.title.no_custom_fields']  = 'No custom fields defined in Cerb';
$lang['cerberus.admin.departments.message.created']         = '%s department created';
$lang['cerberus.admin.departments.message.edited']          = '%s department updated';
$lang['cerberus.admin.departments.message.delete']          = 'Department deleted. Note: No tickets were removed from cerb.';

// Client Area List Tickets (index)
$lang['cerberus.client.index.title.open']               = 'Open';
$lang['cerberus.client.index.title.closed']             = 'Closed';
$lang['cerberus.client.index.title.mask']               = 'Ticket ID';
$lang['cerberus.client.index.title.department']         = 'Department';
//$lang['cerberus.client.index.title.created']  = 'Created At'; // unclear if needed not used
$lang['cerberus.client.index.title.updated']            = 'Updated At';
$lang['cerberus.client.index.title.legend']             = 'Ticket Status Legend';
$lang['cerberus.client.index.title.client_response']    = 'Requesting waiting for your response';
$lang['cerberus.client.index.title.staff_response']     = 'Requesting waiting for staff response ';
$lang['cerberus.client.index.button.create']            = 'Create Ticket';

$lang['cerberus.time_since.day']    = '%1$s d'; // %1$s is the number of days
$lang['cerberus.time_since.hour']   = '%1$s hr'; // %1$s is the number of hours
$lang['cerberus.time_since.minute'] = '%1$s min'; // %1$s is the number of minutes

// Client Area View Ticket
$lang['cerberus.client.ticket.message.no-org']          = 'Helpdesk has not been setup for your account. Missing organization id.';
$lang['cerberus.client.ticket.message.no-dept']         = 'Unable to locate helpdesk department in blesta -- verify if department exists.';
$lang['cerberus.client.ticket.message.404']             = 'Unable to view ticket %s';
$lang['cerberus.client.ticket.header']                  = 'Ticket';
$lang['cerberus.client.ticket.tab.info']                = 'Ticket Information';
$lang['cerberus.client.ticket.subject']                 = 'Subject';
$lang['cerberus.client.ticket.created_by']              = 'Created By';
$lang['cerberus.client.ticket.participants']            = 'Participants';
$lang['cerberus.client.ticket.service']                 = 'Service';
$lang['cerberus.client.ticket.group']                   = 'Group';
$lang['cerberus.client.ticket.status']                  = 'Status';
$lang['cerberus.client.ticket.created']                 = 'Created';
$lang['cerberus.client.ticket.updated']                 = 'Updated';
$lang['cerberus.yes']                                   = 'Yes';
$lang['cerberus.no']                                    = 'No';
$lang['cerberus.client.ticket.message.deleted']         = 'Unable to reply to ticket. Marked for deletion.';
$lang['cerberus.client.ticket.message.archived']        = 'Unable to reply to legacy ticket from old helpdesk. Please open a new request if you are having issues.';
$lang['cerberus.client.ticket.message.reply']           = 'Reply to ticket';
$lang['cerberus.client.ticket.message.placeholder']     = 'Message';
$lang['cerberus.client.ticket.button.reply']            = 'Add Reply';
$lang['cerberus.client.ticket.button.close']            = 'Close Ticket';
$lang['cerberus.client.ticket.message.close']           = 'Are you sure you want to close this ticket? You may always reopen a ticket by replying to it.';
$lang['cerberus.client.ticket.button.cancel']           = 'Cancel';
$lang['cerberus.client.tickets.message.closed']         = 'Ticket %s has been closed. Reply to re-open it.';
$lang['cerberus.client.ticket.message.attachment.404']  = 'Unable to get attachment for ticket: %s ';
$lang['cerberus.client.ticket.message.reply-added']     = 'Message reply added to ticket.';

// Client Area Widget / Dashboard
$lang['cerberus.client.dashboard.open']     = 'Open Tickets';
$lang['cerberus.client.dashboard.waiting']  = 'Tickets Awaiting Your Response';

// Client Area Open Ticket
$lang['cerberus.client.department.title']               = 'Select Department';
$lang['cerberus.client.department.message.404']         = 'No departments have been created.';
$lang['cerberus.client.department.message.no_services'] =  'You do not have any services.';
$lang['cerberus.client.department.title.name']          = 'Name';
$lang['cerberus.client.department.title.email']         = 'Email';
$lang['cerberus.client.department.title.service']       = 'Services';
$lang['cerberus.client.department.title.subject']       = 'Subject';
$lang['cerberus.client.department.title.message']       = 'Message';
$lang['cerberus.client.ticket.message.created']         = 'Ticket successfully created.';

// Generic Error Messages
$lang['cerberus.admin.config.error.post']       = 'Page requires a POST method';
$lang['cerberus.admin.config.error.generic']    = 'Bad URL'; // unsure if used
