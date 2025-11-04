insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DIGEST_ENABLE', 1, 'false', 'Enable email digest',1,
'This option enables sending daily digest of new (and unread) headlines on your configured email address');

alter table ttrss_feeds add column include_in_digest boolean;
update ttrss_feeds set include_in_digest = true;
alter table ttrss_feeds alter column include_in_digest set not null;
alter table ttrss_feeds alter column include_in_digest set default true;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('CONFIRM_FEED_CATCHUP', 1, 'true', 'Confirm marking feed as read',3);

update ttrss_version set schema_version = 10;

