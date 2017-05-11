#
# Cookbook Name:: fileserver
# Recipe:: default
#
# Copyright 2016, GigaScience
#

include_recipe 'user'
include_recipe 'postgresql::server'
include_recipe 'vsftpd'
include_recipe 'cron'


####################################
#### Set up PostgreSQL database ####
####################################

# Generate SQL script to create FTP users database
template "/vagrant/sql/ftpusers.sql" do
    source "ftpusers.sql.erb"
    mode "0644"
end

db = node[:fileserver][:db]
host = node[:fileserver][:db][:host]
if host == 'localhost'
    db_user = db[:user]

    postgresql_user db_user do
        password db[:password]
    end

    postgresql_database db[:database] do
        owner db_user
    end

    bash 'Restore ftpusers database' do
        db_user = db[:user]
        password = db[:password]
        database = db[:database]
        sql_script = '/vagrant/sql/ftpusers.sql'

        code <<-EOH
            export PGPASSWORD='#{password}'; psql -U #{db_user} -h localhost #{database} < #{sql_script}
        EOH
    end
end

case node.chef_environment
when 'development'
	include_recipe 'nfs::client4'

	# iptables not required for default development environment
    service 'iptables' do
        action [:disable, :stop]
    end

    # Install vim and tree
    ['vim', 'tree'].each do |pkg|
        package pkg
    end

	################################
    #### Set up users and group ####
    ################################

    # Create user accounts
    user1 = node[:gigadb][:user1]
    user1_name = node[:gigadb][:user1_name]
    user1_public_key = node[:gigadb][:user1_public_key]

    user_account node[:gigadb][:user1] do
        comment   node[:gigadb][:user1_name]
        ssh_keys  node[:gigadb][:user1_public_key]
        home      "/home/#{node[:gigadb][:user1]}"
    end

    user2 = node[:gigadb][:user2]
    user2_name = node[:gigadb][:user2_name]
    user2_public_key = node[:gigadb][:user2_public_key]

    user_account node[:gigadb][:user2] do
        comment   node[:gigadb][:user2_name]
        ssh_keys  node[:gigadb][:user2_public_key]
        home      "/home/#{node[:gigadb][:user2]}"
    end

    admin_user = node[:gigadb][:admin_user]
    admin_user_name = node[:gigadb][:admin_user_name]
    admin_user_public_key = node[:gigadb][:admin_user_public_key]

    user_account node[:gigadb][:admin_user] do
        comment   node[:gigadb][:admin_user_name]
        ssh_keys  node[:gigadb][:admin_user_public_key]
        home      "/home/#{node[:gigadb][:admin_user]}"
    end

    # Create group for GigaDB admins
    group 'gigadb-admin' do
      action    :create
      members   [user1, user2, admin_user]
      append    true
    end

    group 'wheel' do
        action  :modify
        members [user1, user2, admin_user]
        append  true
    end

	############################
    #### Set up NFS folders ####
    ############################

    log 'message' do
    	message 'Mounting test folders'
      	level :info
    end

    test_mount_point = node[:fileserver][:test_mount_point]
    directory test_mount_point do
      action :create
    end

    # Test mount resource in Chef by mounting /opt/chef onto /mnt/chef
    remote_folder = node[:fileserver][:test_device]
    mount test_mount_point do
      device remote_folder
      fstype 'none'
      options 'bind,rw'
      action [:mount, :enable]
    end

    ###############################################
    #### Add data for testing on VSFTPD server ####
    ###############################################

	# Location of pub data on FTP server
    local_root = node[:vsftpd][:config][:local_root]
    bash 'Create local root directory' do
        code <<-EOH
            mkdir #{local_root}
            chown -R ftp:ftp #{local_root}
            chmod -R u-w #{local_root}
        EOH
    end

    bash 'Create test data' do
        code <<-EOH
            echo "stuff" >#{local_root}/foo.txt
            chown ftp:ftp #{local_root}/foo.txt
        EOH
    end

    temp_upload_dir = "#{test_mount_point}/temporary_upload"
    directory temp_upload_dir do
      owner 'ftp'
      group 'ftp'
      mode '0755'
      action :create
    end

    ######################################################
    #### Install update_ftpusers script from template ####
    ######################################################

    directory '/usr/local/fileserver/bin' do
      owner 'root'
      group 'root'
      mode '0755'
      recursive true
      action :create
    end

    template "/usr/local/fileserver/bin/update_ftpusers.sh" do
        source "update_ftpusers.sh.erb"
        owner 'root'
        group 'root'
        mode 0700
    end

when 'production'
	########################################
	#### Check location of GigaDB data #####
	########################################

	data_location = node[:fileserver][:gigadb_data_location]
	log 'message' do
  		message 'GigaDB data files do not exist!'
  		level :info
  		not_if { ::Dir.exist?(data_location) }
	end

	##############################################
	#### Check location of user upload files #####
	##############################################
	# Check temporary upload directory for users
	user1_temp_upload_dir = "#{test_mount_point}/temporary_upload/user1"
	log 'message' do
		message 'File upload folder for user1 does not exist!'
		level :info
		not_if { ::Dir.exist?(user1_temp_upload_dir) }
	end

	######################################################
	#### Install update_ftpusers script from template ####
	######################################################

	directory '/usr/local/fileserver/bin' do
	  owner 'root'
	  group 'root'
	  mode '0755'
	  recursive true
	  action :create
	end

	# update_ftpusers.sh script creates the user upload dirs and
	# VSFTPD user config files
	template "/usr/local/fileserver/bin/update_ftpusers.sh" do
		source "update_ftpusers.sh.erb"
		owner 'root'
		group 'root'
		mode 0700
	end
end

#########################################
#### Set up ftp user synchronisation ####
#########################################

cron 'FTP users synchronisation cron job' do
    minute '*'
    hour '*'
    day '*'
    month '*'
    shell '/bin/bash'
    path '/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/bin'
    user 'root'
    command '/usr/local/fileserver/bin/update_ftpusers.sh > /dev/null'
end

bash 'restart cron service' do
    code <<-EOH
        service crond restart
    EOH
end


