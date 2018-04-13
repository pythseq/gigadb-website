# -*- mode: ruby -*-
# vi: set ft=ruby :

FTP_SERVER_SCRIPT = <<EOF.freeze
  echo "Preparing FTP server..."
EOF

if ENV['GIGADB_BOX'] == 'ubuntu'
  # Use trusty32 Ubuntu-14.04 box
  box = "trusty32"
  box_url = "https://atlas.hashicorp.com/ubuntu/boxes/trusty64/versions/14.04/providers/virtualbox.box"
elsif ENV['GIGADB_BOX'] == 'docker'
  box = "gigasci/ubuntu-16.04-amd64"
  box_url = "https://atlas.hashicorp.com/gigasci/boxes/ubuntu-16.04-amd64/versions/2018.01.29/providers/virtualbox.box"
elsif ENV['GIGADB_BOX'] == 'aws'
  box = "dummy"
  box_url = "https://github.com/mitchellh/vagrant-aws/raw/master/dummy.box"
else
  box = "nrel/CentOS-6.7-x86_64"
  box_url = "https://atlas.hashicorp.com/nrel/boxes/CentOS-6.7-x86_64/versions/1.0.0/providers/virtualbox.box"
end

def set_hostname(server)
  server.vm.provision 'shell', inline: "hostname #{server.vm.hostname}"
end

if ENV['GIGADB_BOX'] == 'docker'  # Install gigadb-website as Docker app on Ubuntu VM
  Vagrant.configure("2") do |docker|
    docker.vm.box = "gigasci/ubuntu-16.04-amd64.box"
    docker.vm.box_version = "2018.01.29"
    # Sync gigadb-website folder to /vagrant dir on VM
    docker.vm.synced_folder ".", "/vagrant"
    # Allow host to access docker daemon
    docker.vm.network "forwarded_port", guest: 2376, host: 9172
    # Allocate IP address to Ubuntu VM
    docker.vm.network "private_network", ip: "192.168.42.10"

    # Folders required by Yii
    FileUtils.mkpath("./protected/runtime")
    FileUtils.chmod_R 0777, ["./protected/runtime"]
    FileUtils.mkpath("./assets")
    FileUtils.chmod_R 0777, ["./assets"]

    # Run script to download Yii and generate config files in VM
    docker.vm.provision "shell", path: "./yii2-laradock/generate_config.sh"

    # Enable docker daemon access from host on port 9172 forwarded to port 2376
    # in container
    docker.vm.provision :shell, inline: <<-EOT
      sed -i 's|^ExecStart=/usr/bin/dockerd -H fd://|ExecStart=/usr/bin/dockerd -H fd:// -H tcp://0.0.0.0:2376|' /lib/systemd/system/docker.service
      systemctl daemon-reload
      systemctl restart docker.service
    EOT

    docker.vm.provider "virtualbox" do |v|
      # Unless synced_folder's nfs_udp is set to false (which slows things
      # down considerably - up to 50%) DO NOT change --nictype2 to virtio
      # (otherwise writes may freeze)
      v.customize ["modifyvm", :id, "--nictype1", "virtio" ]
      v.customize ["modifyvm", :id, "--natdnshostresolver1", "on"]
      v.customize ["modifyvm", :id, "--natdnsproxy1", "on"]
      v.customize ["modifyvm", :id, "--memory", "1024"]
    end
  end
else  # Install gigadb-website directly on VM or AWS
  Vagrant.configure(2) do |config|
    # Cache packages to reduce provisioning time
    if Vagrant.has_plugin?("vagrant-cachier")
      #Configure cached packages to be shared between instances of the same base box
      config.cache.scope = :box
    end

    config.vm.define 'gigadb-website' do |gigadb|
  	  gigadb.vm.box = box
  	  gigadb.vm.box_url = box_url
  	  gigadb.vm.hostname = 'gigadb-server.test'
  	  set_hostname(gigadb)

      # Forward ports from guest to host, which allows for outside computers
      # to access VM, whereas host only networking does not.
  	  gigadb.vm.network "forwarded_port", guest: 80, host: 9170
  	  gigadb.vm.network "forwarded_port", guest: 5432, host: 9171
	  # Set up directories
  	  gigadb.vm.synced_folder ".", "/vagrant"
  	  FileUtils.mkpath("./protected/runtime")
  	  FileUtils.chmod_R 0777, ["./protected/runtime"]
      FileUtils.mkpath("./images/tempcaptcha")
      FileUtils.chmod_R 0777, ["./images/tempcaptcha"]
  	  FileUtils.mkpath("./giga_cache")
  	  FileUtils.chmod_R 0777, ["./giga_cache"]
  	  FileUtils.mkpath("./logs")
  	  FileUtils.chmod_R 0777, ["./logs"]
  	  # CentOS-specific Vagrant configuration to allow Yii assets folder
  	  # to be world-readable.
  	  if ENV['GIGADB_BOX'] == 'aws' # For CentOS VM and AWS instance
        FileUtils.mkpath("./assets")
        FileUtils.chmod_R 0777, ["./assets"]
      else
        FileUtils.mkpath("./assets")
        gigadb.vm.synced_folder "./assets/", "/vagrant/assets",
          :mount_options => ["dmode=777,fmode=777"]
      end

      ####################
      #### VirtualBox ####
      ####################
      gigadb.vm.provider :virtualbox do |vb|
	    vb.customize ["setextradata", :id, "VBoxInternal2/SharedFoldersEnableSymlinksCreate//vagrant","1"]

	    # Share an additional folder to the guest VM. The first argument is
	    # an identifier, the second is the path on the guest to mount the
	    # folder, and the third is the path on the host to the actual folder.
	    # config.vm.share_folder "v-data", "/vagrant_data", "../data"
	    apt_cache = "./apt-cache"
	    if File.directory?(apt_cache)
	      config.vm.share_folder "apt_cache", "/var/cache/apt/archives", apt_cache
	    end
      end

      #############
      #### AWS ####
      #############
      gigadb.vm.provider :aws do |aws, override|
        aws.access_key_id = ENV['AWS_ACCESS_KEY_ID']
        aws.secret_access_key = ENV['AWS_SECRET_ACCESS_KEY']
        aws.keypair_name = ENV['AWS_KEYPAIR_NAME']
        # aws.ami = "ami-1bfa2b78" # selinux disabled
        aws.ami = "ami-b85e86db" # selinux on
        aws.region = ENV['AWS_DEFAULT_REGION']
        aws.instance_type = "t2.micro"
        aws.tags = {
          'Name' => 'gigadb-website',
          'Deployment' => 'test',
        }
        aws.security_groups = ENV['AWS_SECURITY_GROUPS']

        override.ssh.username = "centos"
        override.ssh.private_key_path = ENV['AWS_SSH_PRIVATE_KEY_PATH']
      end

      # Enable provisioning with chef solo
      gigadb.vm.provision :chef_solo do |chef|
        chef.cookbooks_path = [
          "chef/site-cookbooks",
          "chef/chef-cookbooks",
        ]
        chef.environments_path = 'chef/environments'
        ####################################################
        #### Set server environment: development or aws ####
        ####################################################
        chef.environment = "development"

        chef.data_bags_path = 'chef/data_bags'
        if File.exist?('chef/.chef/encrypted_data_bag_secret')
	      chef.encrypted_data_bag_secret_key_path = 'chef/environments/encrypted_data_bag_secret'
	    end

        if ENV['GIGADB_BOX'] == 'aws'
          chef.add_recipe "aws"
        else
          chef.add_recipe "vagrant"
        end

        # You may also specify custom JSON attributes:
        chef.json = {
          :gigadb_box => ENV['GIGADB_BOX'],
          :environment => "vagrant",
          :gigadb => {
            :server_names => ["localhost"],
            :root_dir => "/vagrant",
            :site_dir => "/vagrant",
            :log_dir => "/vagrant/logs",
            :yii_path => "/opt/yii-1.1.10/framework/yii.php",
          },
          :nginx => {
            :version => :latest,
          },
          :postgresql => {
            :version => "9.1",
            :repo_version => "8.4",
            :dir => '/var/lib/pgsql/9.1/data',
          },
          :elasticsearch => {
            :version => '1.3.4',
          },
          :java => {
            #:install_flavor => 'oracle',
            :jdk_version => '7',
            :oracle => {
               "accept_oracle_download_terms" => true,
            },
          },
        }

        # Additional chef settings to put in solo.rb
        chef.custom_config_path = "Vagrantfile.chef"
      end
    end

    # GigaDB's FTP-server
    if ENV['DEPLOY_GIGADB_FTP'] == 'true'
      config.vm.define 'ftp-server' do |ftp|
	    ftp.vm.box = 'nrel/CentOS-6.7-x86_64'
	    # ftp.vm.box_version = '2.2.9'
	    ftp.vm.hostname = 'ftp-server.test'
	    ftp.vm.network 'private_network', ip: '10.1.1.33'
	    ftp.vm.provision 'shell', inline: FTP_SERVER_SCRIPT.dup
	    set_hostname(ftp)

	    ftp.vm.provision :chef_solo do |ftp_chef|
	      ftp_chef.cookbooks_path = [
	        "chef/site-cookbooks",
	        "chef/chef-cookbooks",
	      ]
	      ftp_chef.environments_path = 'chef/environments'

	      # Set server environment: development
	      ftp_chef.environment = "development"
	      ftp_chef.add_recipe "fileserver"
	      ftp_chef.add_recipe "fileserver::examples"
          ftp_chef.add_recipe "fileserver::bundles"
          ftp_chef.add_recipe "worker"
	    end
      end
    end

    if ENV['DEPLOY_GIGADB_QUEUES'] == 'true'
      config.vm.define 'queues-server' do |queues|
        queues.vm.box = 'nrel/CentOS-6.7-x86_64'
        # queues.vm.box_version = '2.2.9'
        queues.vm.hostname = 'queues-server.test'
        queues.vm.network 'private_network', ip: '10.1.1.35'
        set_hostname(queues)

        queues.vm.provision :chef_solo do |queues_chef|
          queues_chef.cookbooks_path = [
            "chef/site-cookbooks",
            "chef/chef-cookbooks",
          ]
          queues_chef.environments_path = 'chef/environments'

          # Set server environment: development
          queues_chef.environment = "development"
          queues_chef.add_recipe "queues"
        end
      end
    end
  end
end