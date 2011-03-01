#!/bin/bash
######## config #############
# NOTE: xpath from the package perl-XML-XPath is REQUIRED!!
#       curl comandline is also required
rhev_api_url="https://rhevm.example.com:8443/rhevm-api-powershell"
rhev_api_auth="user@domain:password"
######## end config #########

rest_call() {
	method=$1	
	path=$2
	body=$3
	url="${rhev_api_url}${path}"
	curl --insecure --request $method --data "$body" --user $rhev_api_auth --basic --url $url --silent --show-error --header "Accept: application/xml" --header "Content-type: application/xml"
}

if [ -z "$2" ]; then
	echo "Usage: $0 <vmname> <action>"
	echo "   <vmname>:  Name of the VM"	
	echo "   <action>:  status, start, stop, shutdown, suspend"
	exit 1
fi

vmname=$1
action=$2

# get the vmid from a supplied vm_name
vmid=`rest_call "GET" "/vms?search=${vmname}" | xpath "string(/vms/vm/@id)" 2> /dev/null`

if [ -z "$vmid" ]; then
	echo "Error: could not find vm with name '$vmname'"; exit 1
fi
echo "vmid = $vmid"

case "$action" in
	status)
		status=`rest_call "GET" "/vms/$vmid" | xpath "/vm/status/text()" 2> /dev/null`
		echo "VM $vmname status = $status"
		;;
	*)
		result=`rest_call "POST" "/vms/$vmid/$action" "<action/>" | xpath "/action/status/text()" 2> /dev/null`
		echo "VM '$vmname' action '$action' result: $result"
		;;
esac




