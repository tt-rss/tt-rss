insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('DIGEST_CATCHUP', 1, 'false', 'Mark articles in email digest as read',1);

update ttrss_version set schema_version = 23;
