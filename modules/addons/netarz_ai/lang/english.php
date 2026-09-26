<?php
/**
 * NetArz AI for WHMCS — English.
 * :name placeholders are replaced at run time.
 */

$_ADDONLANG['_language'] = 'english';

/* module */
$_ADDONLANG['module_title'] = 'NetArz AI';
$_ADDONLANG['module_subtitle'] = 'AI live chat and ticket replies';
$_ADDONLANG['default_agent_name'] = 'Support assistant';
$_ADDONLANG['tab_dashboard'] = 'Dashboard';
$_ADDONLANG['tab_inbox'] = 'Live chat';
$_ADDONLANG['tab_tickets'] = 'Tickets';
$_ADDONLANG['tab_knowledge'] = 'Knowledge';
$_ADDONLANG['tab_settings'] = 'Settings';
$_ADDONLANG['tab_logs'] = 'Log';
$_ADDONLANG['foot_docs'] = 'API docs';
$_ADDONLANG['foot_panel'] = 'NetArz panel';

/* setup */
$_ADDONLANG['setup_title'] = 'Connect your NetArz API key';
$_ADDONLANG['setup_text'] = 'The chat and the ticket replies run on your NetArz AI credit. Create a key in the NetArz panel, paste it in Settings, and the assistant starts working.';
$_ADDONLANG['setup_get_key'] = 'Get an API key';
$_ADDONLANG['setup_open_settings'] = 'Open settings';

/* dashboard */
$_ADDONLANG['dash_balance'] = 'AI credit';
$_ADDONLANG['dash_toman'] = 'About :amount Toman';
$_ADDONLANG['dash_topup'] = 'Top up';
$_ADDONLANG['dash_no_key'] = 'No API key yet.';
$_ADDONLANG['dash_today'] = 'Spent today';
$_ADDONLANG['dash_calls'] = ':n requests';
$_ADDONLANG['dash_budget_of'] = 'daily cap :cap';
$_ADDONLANG['dash_chats'] = 'Open chats';
$_ADDONLANG['dash_waiting'] = ':n waiting for a person';
$_ADDONLANG['dash_open_inbox'] = 'Open the inbox';
$_ADDONLANG['dash_tickets'] = 'Tickets handled this month';
$_ADDONLANG['dash_ticket_mode'] = 'Ticket mode';
$_ADDONLANG['dash_handoffs'] = ':n handed to your team';
$_ADDONLANG['dash_chart'] = 'Spend, last 14 days';
$_ADDONLANG['dash_chart_note'] = 'Estimated from token counts and public prices. The exact figure is in your NetArz panel.';
$_ADDONLANG['dash_recent'] = 'Latest ticket activity';
$_ADDONLANG['dash_recent_empty'] = 'Nothing yet. New tickets show up here once the ticket mode is on.';
$_ADDONLANG['dash_project'] = 'Project';
$_ADDONLANG['dash_key'] = 'Key';
$_ADDONLANG['dash_rpm'] = 'Requests per minute';
$_ADDONLANG['dash_model'] = 'Model';
$_ADDONLANG['dash_month'] = 'This month';
$_ADDONLANG['see_all'] = 'See all';
$_ADDONLANG['refresh'] = 'Refresh';

/* inbox */
$_ADDONLANG['inbox_open'] = 'Open';
$_ADDONLANG['inbox_waiting'] = 'Waiting';
$_ADDONLANG['inbox_closed'] = 'Closed';
$_ADDONLANG['inbox_all'] = 'All';
$_ADDONLANG['inbox_empty'] = 'No conversations here.';
$_ADDONLANG['inbox_pick'] = 'Pick a conversation on the left.';
$_ADDONLANG['inbox_takeover'] = 'Take over';
$_ADDONLANG['inbox_takeover_hint'] = 'The AI stops answering this visitor. Replying does the same.';
$_ADDONLANG['inbox_handback'] = 'Back to AI';
$_ADDONLANG['inbox_handback_hint'] = 'Let the AI answer this visitor again.';
$_ADDONLANG['inbox_to_ticket'] = 'Make a ticket';
$_ADDONLANG['inbox_close'] = 'Close';
$_ADDONLANG['inbox_delete'] = 'Delete conversation';
$_ADDONLANG['inbox_confirm_delete'] = 'Delete this conversation for good?';
$_ADDONLANG['inbox_placeholder'] = 'Write a reply…';
$_ADDONLANG['inbox_composer_hint'] = 'Enter sends, Shift+Enter adds a line. Your reply takes the conversation over from the AI.';
$_ADDONLANG['inbox_ticket_done'] = 'Ticket opened';
$_ADDONLANG['inbox_handoff_reason'] = 'Handed over';
$_ADDONLANG['inbox_online'] = 'on the page now';
$_ADDONLANG['mode_ai_short'] = 'AI';
$_ADDONLANG['mode_human_short'] = 'Staff';
$_ADDONLANG['mode_waiting_short'] = 'Waiting';
$_ADDONLANG['visitor'] = 'Visitor';
$_ADDONLANG['client'] = 'Client';
$_ADDONLANG['guest'] = 'Guest';
$_ADDONLANG['seen'] = 'Seen';
$_ADDONLANG['send'] = 'Send';
$_ADDONLANG['back'] = 'Back';

/* tickets */
$_ADDONLANG['tickets_title'] = 'AI on tickets';
$_ADDONLANG['tickets_intro'] = 'Every new ticket and customer reply in the chosen departments is read by the AI. What happened to each one is listed here.';
$_ADDONLANG['tickets_empty'] = 'No ticket activity yet.';
$_ADDONLANG['filter_all'] = 'All';
$_ADDONLANG['col_ticket'] = 'Ticket';
$_ADDONLANG['col_event'] = 'Event';
$_ADDONLANG['col_outcome'] = 'Result';
$_ADDONLANG['col_reason'] = 'Why';
$_ADDONLANG['col_time'] = 'Time';
$_ADDONLANG['col_channel'] = 'Channel';
$_ADDONLANG['col_ref'] = 'Ref';
$_ADDONLANG['col_tokens'] = 'Tokens in / out';
$_ADDONLANG['col_cost'] = 'Cost';
$_ADDONLANG['col_latency'] = 'Time taken';
$_ADDONLANG['col_result'] = 'Result';
$_ADDONLANG['confidence'] = 'Confidence';
$_ADDONLANG['view_draft'] = 'Draft';
$_ADDONLANG['retry'] = 'Try again';
$_ADDONLANG['event_open'] = 'New ticket';
$_ADDONLANG['event_reply'] = 'Customer reply';
$_ADDONLANG['event_manual'] = 'Asked by staff';
$_ADDONLANG['mode_off'] = 'Off';
$_ADDONLANG['mode_off_hint'] = 'The AI does not read tickets.';
$_ADDONLANG['mode_draft'] = 'Drafts for staff';
$_ADDONLANG['mode_draft_hint'] = 'The AI writes a reply on the ticket page. A person reviews it and sends it.';
$_ADDONLANG['mode_auto'] = 'Answer automatically';
$_ADDONLANG['mode_auto_hint'] = 'Confident answers are sent to the customer. Anything uncertain stays a draft with a note for your team.';
$_ADDONLANG['outcome_replied'] = 'Answered';
$_ADDONLANG['outcome_drafted'] = 'Draft ready';
$_ADDONLANG['outcome_handoff'] = 'Needs a person';
$_ADDONLANG['outcome_pending'] = 'Queued';
$_ADDONLANG['outcome_processing'] = 'Working';
$_ADDONLANG['outcome_retry'] = 'Will retry';
$_ADDONLANG['outcome_superseded'] = 'Replaced by a newer message';
$_ADDONLANG['outcome_staff_replied'] = 'Staff answered';
$_ADDONLANG['outcome_paused'] = 'AI paused on ticket';
$_ADDONLANG['outcome_closed'] = 'Ticket closed';
$_ADDONLANG['outcome_already_answered'] = 'Already answered';
$_ADDONLANG['outcome_empty'] = 'Empty ticket';
$_ADDONLANG['outcome_ticket_not_found'] = 'Ticket not found';
$_ADDONLANG['outcome_low_balance'] = 'Credit too low';
$_ADDONLANG['outcome_budget'] = 'Daily cap reached';
$_ADDONLANG['outcome_missing_api_key'] = 'No API key';
$_ADDONLANG['outcome_invalid_api_key'] = 'API key rejected';
$_ADDONLANG['outcome_insufficient_credit'] = 'Out of credit';
$_ADDONLANG['outcome_exception'] = 'Error';
$_ADDONLANG['outcome_skipped'] = 'Skipped';
$_ADDONLANG['outcome_failed'] = 'Failed';
$_ADDONLANG['outcome_done'] = 'Done';
$_ADDONLANG['outcome_network_error'] = 'Connection error';
$_ADDONLANG['outcome_upstream_error'] = 'Provider error';
$_ADDONLANG['outcome_rate_limit_exceeded'] = 'Rate limited';

/* ticket page panel */
$_ADDONLANG['tp_title'] = 'AI reply';
$_ADDONLANG['tp_generate'] = 'Write a draft';
$_ADDONLANG['tp_regenerate'] = 'Write again';
$_ADDONLANG['tp_use'] = 'Put in reply box';
$_ADDONLANG['tp_dismiss'] = 'Dismiss';
$_ADDONLANG['tp_pause'] = 'Pause AI here';
$_ADDONLANG['tp_resume'] = 'Resume AI';
$_ADDONLANG['tp_paused_note'] = 'The AI is paused on this ticket and will not reply to it.';
$_ADDONLANG['tp_none'] = 'No draft for this ticket yet.';
$_ADDONLANG['tp_nothing'] = 'The AI had nothing to suggest.';
$_ADDONLANG['tp_working'] = 'Reading the ticket and writing a draft…';
$_ADDONLANG['tp_inserted'] = 'Draft placed in the reply box. Check it before you send.';

/* knowledge */
$_ADDONLANG['kn_title'] = 'What the assistant knows';
$_ADDONLANG['kn_intro'] = 'For each question the assistant picks the most relevant parts of these sources. It never answers from anything else.';
$_ADDONLANG['kn_src_kb'] = 'Knowledgebase — :n public articles';
$_ADDONLANG['kn_src_ann'] = 'Announcements — :n published';
$_ADDONLANG['kn_src_products'] = 'Products and prices from the order form';
$_ADDONLANG['kn_src_domains'] = 'Domain prices';
$_ADDONLANG['kn_src_network'] = 'Open network issues';
$_ADDONLANG['kn_src_client'] = 'The signed-in client\'s own services, domains and unpaid invoices';
$_ADDONLANG['kn_custom'] = 'Your notes for the assistant';
$_ADDONLANG['kn_custom_hint'] = 'Policies, working hours, refund rules, server details, answers to common questions. This text wins over everything else.';
$_ADDONLANG['kn_custom_placeholder'] = "Support hours: Saturday to Wednesday, 9:00–17:00.\nRefunds: within 7 days for shared hosting, never for domains.\nNameservers: ns1.example.com, ns2.example.com";
$_ADDONLANG['kn_console'] = 'Try the assistant';
$_ADDONLANG['kn_console_intro'] = 'Ask what your customers ask. You see the reply, the decision and the cost before any customer does.';
$_ADDONLANG['kn_console_placeholder'] = 'How much is a year of the smallest hosting plan?';
$_ADDONLANG['kn_console_as'] = 'As';
$_ADDONLANG['kn_console_client'] = 'Client ID';
$_ADDONLANG['kn_channel_chat'] = 'Live chat';
$_ADDONLANG['kn_channel_ticket'] = 'Ticket';
$_ADDONLANG['kn_preview'] = 'Show retrieved knowledge';
$_ADDONLANG['kn_ask'] = 'Ask';
$_ADDONLANG['save'] = 'Save';

/* settings */
$_ADDONLANG['s_connection'] = 'Connection';
$_ADDONLANG['s_voice'] = 'Assistant';
$_ADDONLANG['s_chat'] = 'Live chat';
$_ADDONLANG['s_tickets'] = 'Tickets';
$_ADDONLANG['s_knowledge'] = 'Knowledge sources';
$_ADDONLANG['s_money'] = 'Budget and data';
$_ADDONLANG['save_settings'] = 'Save settings';
$_ADDONLANG['settings_saved'] = 'Settings saved.';
$_ADDONLANG['f_api_key'] = 'NetArz API key';
$_ADDONLANG['f_api_key_hint'] = 'Starts with sk-ntz-. Stored encrypted. Create one in your NetArz panel:';
$_ADDONLANG['f_test'] = 'Test connection';
$_ADDONLANG['f_model'] = 'Model';
$_ADDONLANG['f_model_hint'] = 'Any chat model from the NetArz catalogue. gpt-4o-mini is fast and inexpensive for support.';
$_ADDONLANG['f_base_url'] = 'API address';
$_ADDONLANG['f_base_url_hint'] = 'Leave as it is unless NetArz support asks you to change it.';
$_ADDONLANG['f_temperature'] = 'Creativity';
$_ADDONLANG['f_temperature_hint'] = 'Lower is more predictable. 0.2–0.4 suits support.';
$_ADDONLANG['f_max_tokens'] = 'Longest reply (tokens)';
$_ADDONLANG['f_max_tokens_hint'] = 'Caps the cost of a single answer.';
$_ADDONLANG['f_agent_name'] = 'Assistant name';
$_ADDONLANG['f_agent_name_hint'] = 'Shown in the chat header and next to AI replies.';
$_ADDONLANG['f_brand_name'] = 'Company name';
$_ADDONLANG['f_brand_name_hint'] = 'The assistant speaks for this company. Empty = your WHMCS company name.';
$_ADDONLANG['f_language'] = 'Reply language';
$_ADDONLANG['f_language_hint'] = 'Automatic replies in the customer\'s own language.';
$_ADDONLANG['lang_auto'] = 'Automatic';
$_ADDONLANG['lang_fa'] = 'Persian';
$_ADDONLANG['lang_en'] = 'English';
$_ADDONLANG['f_tone'] = 'Tone';
$_ADDONLANG['tone_friendly'] = 'Friendly';
$_ADDONLANG['tone_formal'] = 'Formal';
$_ADDONLANG['f_instructions'] = 'Extra instructions';
$_ADDONLANG['f_instructions_hint'] = 'How the assistant should behave. Facts belong in Knowledge; this box is for style and rules.';
$_ADDONLANG['f_instructions_placeholder'] = 'Always suggest the annual plan. Never discuss competitors. Sign off with our support phone number.';
$_ADDONLANG['f_chat_enabled'] = 'Show the chat';
$_ADDONLANG['f_chat_enabled_hint'] = 'Adds the chat button to every client-area page.';
$_ADDONLANG['f_chat_ai'] = 'AI answers in chat';
$_ADDONLANG['f_chat_ai_hint'] = 'Off: the chat still works, and your team answers every message.';
$_ADDONLANG['f_chat_guests'] = 'Visitors who are not signed in';
$_ADDONLANG['f_chat_guests_hint'] = 'Off: only signed-in clients can chat.';
$_ADDONLANG['f_chat_guest_email'] = 'Ask visitors for name and email';
$_ADDONLANG['f_chat_guest_email_hint'] = 'Needed to turn a guest chat into a ticket.';
$_ADDONLANG['f_chat_alerts'] = 'Alert staff in the admin area';
$_ADDONLANG['f_chat_alerts_hint'] = 'A small notice on every admin page when a visitor waits for a person.';
$_ADDONLANG['f_chat_credit'] = 'Show "AI by NetArz"';
$_ADDONLANG['f_chat_credit_hint'] = 'A small line under the chat box.';
$_ADDONLANG['f_chat_position'] = 'Button position';
$_ADDONLANG['pos_right'] = 'Bottom right';
$_ADDONLANG['pos_left'] = 'Bottom left';
$_ADDONLANG['f_chat_color'] = 'Main colour';
$_ADDONLANG['f_chat_text_color'] = 'Text on main colour';
$_ADDONLANG['f_chat_burst'] = 'Wait before answering (ms)';
$_ADDONLANG['f_chat_burst_hint'] = 'People type in bursts. The AI waits this long after the last message and answers them together.';
$_ADDONLANG['f_chat_wait'] = 'Offer a ticket after (minutes)';
$_ADDONLANG['f_chat_wait_hint'] = 'If nobody from your team answers a handed-over chat in time, the visitor is offered a ticket.';
$_ADDONLANG['f_chat_rate'] = 'Messages per visitor per hour';
$_ADDONLANG['f_chat_rate_hint'] = 'Stops floods and runaway costs.';
$_ADDONLANG['f_chat_len'] = 'Longest message (characters)';
$_ADDONLANG['f_chat_history'] = 'Messages the AI remembers';
$_ADDONLANG['f_chat_history_hint'] = 'Turns of the conversation sent with each question.';
$_ADDONLANG['f_chat_greeting'] = 'Greeting';
$_ADDONLANG['f_chat_greeting_hint'] = 'The first bubble visitors see. Empty = a default greeting.';
$_ADDONLANG['f_chat_hide'] = 'Hide the chat on these pages';
$_ADDONLANG['f_chat_hide_hint'] = 'One address part per line, for example cart.php?a=checkout';
$_ADDONLANG['f_ticket_admin'] = 'Post replies as';
$_ADDONLANG['f_ticket_admin_hint'] = 'The admin user whose name appears on AI replies. Tip: create an admin called "Support assistant".';
$_ADDONLANG['f_ticket_status'] = 'Ticket status after an AI reply';
$_ADDONLANG['status_default'] = 'WHMCS default';
$_ADDONLANG['f_ticket_conf'] = 'Minimum confidence to send (%)';
$_ADDONLANG['f_ticket_conf_hint'] = 'Below this, the reply stays a draft.';
$_ADDONLANG['f_ticket_max'] = 'AI replies per ticket';
$_ADDONLANG['f_ticket_max_hint'] = 'After this many, a person takes the ticket.';
$_ADDONLANG['f_ticket_delay'] = 'Wait before answering (minutes)';
$_ADDONLANG['f_ticket_delay_hint'] = '0 answers at once. With a delay, answers go out on the WHMCS cron.';
$_ADDONLANG['f_ticket_depts'] = 'Departments';
$_ADDONLANG['f_ticket_depts_hint'] = 'None selected = every department.';
$_ADDONLANG['no_departments'] = 'No departments found.';
$_ADDONLANG['f_ticket_instant'] = 'Answer right away';
$_ADDONLANG['f_ticket_instant_hint'] = 'Works right after the page is sent, without waiting for cron.';
$_ADDONLANG['f_ticket_note'] = 'Leave a note when a person is needed';
$_ADDONLANG['f_ticket_note_hint'] = 'An internal note with the reason and the draft.';
$_ADDONLANG['f_ticket_skip_human'] = 'Step back once staff reply';
$_ADDONLANG['f_ticket_skip_human_hint'] = 'After a person answers, the AI only writes drafts on that ticket.';
$_ADDONLANG['f_ticket_signature'] = 'Signature under AI replies';
$_ADDONLANG['f_ticket_signature_hint'] = 'For example: "This reply was written by our AI assistant. Reply to reach a person."';
$_ADDONLANG['f_kn_kb'] = 'Knowledgebase articles';
$_ADDONLANG['f_kn_ann'] = 'Announcements';
$_ADDONLANG['f_kn_products'] = 'Products and prices';
$_ADDONLANG['f_kn_domains'] = 'Domain prices';
$_ADDONLANG['f_kn_network'] = 'Network issues';
$_ADDONLANG['f_kn_client'] = 'The client\'s own account';
$_ADDONLANG['f_kn_client_hint'] = 'Services, domains and unpaid invoices of the signed-in client. Passwords and credentials are never shared.';
$_ADDONLANG['f_kn_custom_where'] = 'Your own notes are edited under';
$_ADDONLANG['f_budget'] = 'Daily spending cap (USD)';
$_ADDONLANG['f_budget_hint'] = '0 = no cap. When reached, the AI steps aside until tomorrow.';
$_ADDONLANG['f_min_balance'] = 'Keep at least (USD)';
$_ADDONLANG['f_min_balance_hint'] = 'Below this credit the AI steps aside and your team answers.';
$_ADDONLANG['f_retention'] = 'Keep conversations for (days)';
$_ADDONLANG['f_retention_hint'] = 'Older chats and logs are removed by the daily cron.';
$_ADDONLANG['f_alerts'] = 'Email me when credit runs low';
$_ADDONLANG['f_alerts_hint'] = 'At most once a day, to WHMCS admins.';
$_ADDONLANG['danger_title'] = 'Remove all module data';
$_ADDONLANG['danger_text'] = 'Deletes every conversation, draft, log and setting of this module. Type DELETE to confirm.';
$_ADDONLANG['danger_button'] = 'Remove everything';

/* logs */
$_ADDONLANG['logs_title'] = 'Request log';
$_ADDONLANG['logs_intro'] = 'The last 200 requests to the NetArz API. The request ID matches the one in your NetArz panel.';
$_ADDONLANG['logs_empty'] = 'No requests yet.';
$_ADDONLANG['channel_chat'] = 'Chat';
$_ADDONLANG['channel_ticket'] = 'Ticket';
$_ADDONLANG['channel_test'] = 'Test';

/* widget */
$_ADDONLANG['widget_no_key'] = 'Connect a NetArz API key to see your AI credit here.';
$_ADDONLANG['widget_calls'] = 'Requests today';
$_ADDONLANG['widget_waiting'] = 'Chats waiting';
$_ADDONLANG['widget_open'] = 'Open NetArz AI';

/* errors */
$_ADDONLANG['err_no_key'] = 'No NetArz API key is set.';
$_ADDONLANG['err_api_key_format'] = 'That does not look like a NetArz API key. It starts with sk-ntz-.';
$_ADDONLANG['err_base_url'] = 'The API address must start with https://';
$_ADDONLANG['err_color'] = 'Colours must look like #ffc700.';
$_ADDONLANG['err_network'] = 'Could not reach the NetArz API.';
$_ADDONLANG['err_http'] = 'The NetArz API answered with status';
$_ADDONLANG['err_budget'] = 'Today\'s spending cap is reached.';
$_ADDONLANG['err_low_balance'] = 'Your NetArz AI credit is below the minimum you set.';
$_ADDONLANG['err_csrf'] = 'Your session expired. Reload the page and try again.';
$_ADDONLANG['err_empty_question'] = 'Write a question first.';
$_ADDONLANG['err_generic'] = 'Something went wrong. Please try again.';

/* alerts */
$_ADDONLANG['alert_subject'] = 'NetArz AI: your AI credit needs attention';
$_ADDONLANG['alert_low_body'] = "Your NetArz AI credit is :balance. Below :min the assistant stops answering and your team takes over.\n\nTop up here: :url";
$_ADDONLANG['alert_broken_body'] = "The NetArz API refused the key: :error\n\nCheck the key in your NetArz panel: :url";
$_ADDONLANG['alert_toast_title'] = 'A visitor is waiting';
$_ADDONLANG['alert_toast_open'] = 'open the chat';

/* notes written into WHMCS */
$_ADDONLANG['note_handoff'] = "NetArz AI: a person should answer this ticket.\nReason: :reason (confidence :confidence%)";
$_ADDONLANG['note_draft_follows'] = 'Suggested reply:';
$_ADDONLANG['attachment_note'] = 'attachments';

/* chat, server side */
$_ADDONLANG['chat_greeting_default'] = 'Hello, welcome to :brand. How can I help you today?';
$_ADDONLANG['chat_handoff_default'] = 'I have passed your message to a colleague. They will reply here shortly. Please keep this window open.';
$_ADDONLANG['chat_redirect_default'] = 'I can help with questions about :brand services, orders and your account. What would you like to know?';
$_ADDONLANG['secret_warning'] = 'For your security, please change this password now, and never send passwords in chat or tickets.';
$_ADDONLANG['chat_you'] = 'Visitor';
$_ADDONLANG['chat_staff'] = 'Support team';
$_ADDONLANG['chat_ticket_subject'] = 'Live chat — :name';
$_ADDONLANG['chat_ticket_intro'] = 'This ticket was opened from a live chat. The conversation so far:';
$_ADDONLANG['chat_ticket_created'] = 'Ticket #:tid is open. We will answer you there, and by email.';
$_ADDONLANG['chat_closed_note'] = 'This conversation was closed. Write again any time.';

/* chat widget (sent to the browser) */
$_ADDONLANG['w_title'] = 'Support chat';
$_ADDONLANG['w_open'] = 'Open support chat';
$_ADDONLANG['w_minimize'] = 'Minimise';
$_ADDONLANG['w_placeholder'] = 'Write your message…';
$_ADDONLANG['w_send'] = 'Send';
$_ADDONLANG['w_sent'] = 'Sent';
$_ADDONLANG['w_seen'] = 'Seen';
$_ADDONLANG['w_status_ai'] = 'Usually replies in seconds';
$_ADDONLANG['w_status_team'] = 'Our team replies here';
$_ADDONLANG['w_status_waiting'] = 'A colleague will join shortly';
$_ADDONLANG['w_status_human'] = 'Online now';
$_ADDONLANG['w_identity_lead'] = 'So we can reply even if you leave the page:';
$_ADDONLANG['w_name'] = 'Your name';
$_ADDONLANG['w_email'] = 'Your email';
$_ADDONLANG['w_start'] = 'Start chat';
$_ADDONLANG['w_identity_invalid'] = 'Please enter your name and a valid email address.';
$_ADDONLANG['w_login_required'] = 'Please sign in to your account to chat with us.';
$_ADDONLANG['w_login'] = 'Sign in';
$_ADDONLANG['w_offer_ticket'] = 'Nobody is free right now. Turn this chat into a ticket and we will answer you by email.';
$_ADDONLANG['w_make_ticket'] = 'Open a ticket';
$_ADDONLANG['w_view_ticket'] = 'View the ticket';
$_ADDONLANG['w_credit'] = 'AI by NetArz';
$_ADDONLANG['w_err_generic'] = 'Something went wrong. Please try again.';
$_ADDONLANG['w_err_too_long'] = 'This message is too long.';
$_ADDONLANG['w_err_empty'] = 'Write something first.';
$_ADDONLANG['w_err_rate_limited'] = 'You are sending a lot of messages. Please wait a few minutes.';
$_ADDONLANG['w_err_duplicate'] = 'This message was already sent.';
$_ADDONLANG['w_err_no_thread'] = 'This conversation has ended. Send a new message to start again.';
$_ADDONLANG['w_err_identity_required'] = 'Please enter your name and email.';
$_ADDONLANG['w_err_invalid_email'] = 'That email address does not look right.';
$_ADDONLANG['w_err_login_required'] = 'Please sign in to chat with us.';
$_ADDONLANG['w_err_disabled'] = 'The chat is not available right now.';
$_ADDONLANG['w_err_forbidden'] = 'This request was blocked. Reload the page and try again.';
$_ADDONLANG['w_err_unknown_action'] = 'Something went wrong. Please try again.';
$_ADDONLANG['w_err_no_department'] = 'Tickets cannot be opened right now.';
$_ADDONLANG['w_err_email_required'] = 'We need your email address to open a ticket.';
$_ADDONLANG['w_err_ticket_failed'] = 'The ticket could not be opened. Please try again.';

/* why a reply stayed a draft */
$_ADDONLANG['reason_draft_mode'] = 'Draft mode: staff send replies';
$_ADDONLANG['reason_low_confidence'] = 'Not confident enough to send';
$_ADDONLANG['reason_staff_on_ticket'] = 'A staff member is on this ticket';
$_ADDONLANG['reason_auto_reply_cap'] = 'AI reply limit for this ticket reached';
$_ADDONLANG['reason_no_admin_user'] = 'No admin user chosen to post as';
$_ADDONLANG['reason_post_failed'] = 'WHMCS did not accept the reply';
$_ADDONLANG['reason_handoff'] = 'Needs a person';
$_ADDONLANG['reason_malformed_output'] = 'The model answered in the wrong format';
$_ADDONLANG['reason_empty_reply'] = 'The model gave an empty answer';
$_ADDONLANG['reason_model_chose_silence'] = 'The model had nothing to say';
