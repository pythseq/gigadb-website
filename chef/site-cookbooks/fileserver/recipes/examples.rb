# install sample data on the ftp server for testing

examples_file = node[:gigadb][:ftp][:examples_file]

remote_file '/tmp/ftpexamples.tar.gz' do
  source "#{examples_file}"
end

bash 'extract_examples' do
  cwd "/"
  code <<-EOH
    tar xzf /tmp/ftpexamples.tar.gz
    EOH
end
