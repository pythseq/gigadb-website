default['vsftpd']['config'] = {
  'userlist_file'               => node['vsftpd']['etcdir'] + '/vsftpd.user_list',
  'user_config_dir'             => node['vsftpd']['etcdir'] + '/users.d',
  'banned_email_file'           => node['vsftpd']['etcdir'] + '/banned_emails',
  'chroot_list_file'            => node['vsftpd']['etcdir'] + '/vsftpd.chroot_list',
  'ssl_enable'                  => node['vsftpd']['ssl']['enabled'] ? 'YES' : 'NO',
  'allow_anon_ssl'              => node['vsftpd']['ssl']['allow_anon'] ? 'YES' : 'NO',
  'force_local_data_ssl'        => node['vsftpd']['ssl']['force_local_data'] ? 'YES' : 'NO',
  'force_local_logins_ssl'      => node['vsftpd']['ssl']['force_local_logins'] ? 'YES' : 'NO',
  'ssl_tlsv1'                   => node['vsftpd']['ssl']['tslv1_enabled'] ? 'YES' : 'NO',
  'ssl_sslv2'                   => node['vsftpd']['ssl']['sslv2_enabled'] ? 'YES' : 'NO',
  'ssl_sslv3'                   => node['vsftpd']['ssl']['sslv3_enabled'] ? 'YES' : 'NO',
  'rsa_cert_file'               => node['vsftpd']['ssl']['cert']['public_cert_file'],
  'rsa_private_key_file'        => node['vsftpd']['ssl']['key']['private_key_file']
}
