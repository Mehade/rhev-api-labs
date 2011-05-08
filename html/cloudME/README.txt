cloudME (short version of Deltacloud Mobile Edition)

Installation:

$ mkdir cloud
$ cd cloud
$ svn checkout https://svn.apache.org/repos/asf/incubator/deltacloud/trunk/server
$ svn checkout https://rhev-api-labs.googlecode.com/svn/html/cloudME
$ $ ln -s `pwd`/cloudME server/public/
(as root)
# cd server
# ./bin/deltacloudd  -r 192.168.0.193 -p 80 -i rhevm -P https://rhevm.selab.mad.redhat.com:8443/rhevm-api-powershell

Point browser to:

http://192.168.0.193/cloudME/index.html
