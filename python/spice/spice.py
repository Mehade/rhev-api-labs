#!/usr/bin/env python
#
# Licence: http://www.opensource.org/licenses/mit-license.php

import os
import sys
import logging

from rhev import *
from optparse import OptionParser

def parse_args():
    """Parse command-line arguments."""
    parser = OptionParser(usage='%prog [options] <name>',
                          description='create a VM using the SDK')
    parser.add_option('-U', '--url', help='the API entry point URL')
    parser.add_option('-u', '--username', help='the user to connect as')
    parser.add_option('-p', '--password', help='the user\'s password')
    for key in ('url', 'username', 'password'):
        name = 'RHEV_%s' % key.upper()
        parser.set_default(key, os.environ.get(name))
    opts, args = parser.parse_args()
    for key in ('url', 'username', 'password'):
        if getattr(opts, key) is None:
            name = 'RHEV_%s' % key.upper()
            parser.error('please specify --%s or set $%s' % (key, name))
    if len(args) != 1:
        parser.print_usage()
        parser.exit()
    return opts, args
    
opts, args = parse_args()

api = Connection(opts.url, opts.username, opts.password)

try:
    vm = api.get(schema.VM, name=args[0])
    if vm is None:
        raise Error('vm "%s" not found' % args[0])
    if vm.status != "UP":
        raise Error('vm "%s" is not UP' % args[0])
    action_in = schema.new(schema.Action)
    action_in.expiry = 120
    action = api.action(vm, 'ticket', action_in)
    #print 'ticket value: %s, action status: %s' % (action.ticket.value_, action.status)
except Error, e:
    sys.stderr.write('error: %s\n' % str(e))
    sys.exit(1)

# CA cert url available at https://rhevm.example.com/ca.crt
[cert_url, tmp] = opts.url.rsplit(':', 1);
wget = 'wget --no-check-certificate -O - -q %s/ca.crt > ~/.spicec/spice_truststore.pem' % cert_url
os.system(wget)

# Requires spice client 0.6
securePort = 5890 - (vm.display.port - 5910)
spicec = 'spicec -h %s -p %s -s %s -w %s' % (vm.display.address, vm.display.port, securePort, action.ticket.value_)
os.system(spicec)

