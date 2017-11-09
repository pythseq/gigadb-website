FROM centos:6.7
MAINTAINER Peter Li <peter@gigasciencejournal.com>

# Install httpd
#RUN yum -y install httpd && echo "Apache HTTPD" >> /var/www/html/index.html


# wheel_tty.sh #
# Allows wheel group to run all commands without password and tty
#RUN sed -e "/^#/ {/%wheel/s/^# *//}" -i /etc/sudoers
#RUN sed -e "/^#/! {/requiretty/s/^/# /}" -i /etc/sudoers

# common.sh #
#RUN yum -y groupinstall \
#	base \
#	core

#RUN yum -y update && yum -y install \
#	autofs \
#	bind-utils \
#	bzip2 \
#	deltarpm \
#  epel-release \
#	mlocate \
#	ntp \
#	wget \
#	nfs-utils \
#	unzip \
#	yum-plugin-remove-with-leaves \
#	yum-utils

RUN curl -LO https://www.opscode.com/chef/install.sh && bash ./install.sh -v 12.18.31

COPY ./chef /chef

WORKDIR /chef

#RUN /bin/bash -c 'for f in $(ls *gz); do tar -zxf $f; rm $f; done'

#RUN /usr/bin/chef-solo -c /chef/docker-chef-solo/config.rb -j /chef/docker-chef-solo/attributes.json
#RUN /usr/bin/chef-solo -c /chef/docker-chef-solo/solo.rb -j /chef/docker-chef-solo/node.json

# Disable iptables on boot up
RUN yum -y install iptables
RUN chkconfig iptables off

#CMD /usr/sbin/service apache2 start

EXPOSE 80


#CMD ["/usr/bin/chef-solo -c /vagrant/chef/docker-chef-solo/solo.rb -j /vagrant/chef/docker-chef-solo/node.json"]
## Simple startup script to avoid some issues observed with container restart
#ADD run-chef.sh /run-chef.sh
#RUN chmod -v +x /run-chef.sh
#CMD ["/run-chef.sh"]
CMD ["/bin/bash"]
##################

#RUN echo "Apache HTTPD by Chef" >> /var/www/html/index.html
#
## Simple startup script to avoid some issues observed with container restart
#ADD run-httpd.sh /run-httpd.sh
#RUN chmod -v +x /run-httpd.sh
#CMD ["/run-httpd.sh"]
