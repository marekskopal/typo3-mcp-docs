CREATE TABLE tx_msmcpdocs_oauth_token (
    content_element_uid int(11) DEFAULT 0 NOT NULL,
    server_url varchar(2048) DEFAULT '' NOT NULL,
    client_id varchar(255) DEFAULT '' NOT NULL,
    access_token text,
    refresh_token text,
    expires_at int(11) DEFAULT 0 NOT NULL,

    UNIQUE KEY content_element (content_element_uid)
);
