#!/usr/bin/env python
#
# License: http://www.opensource.org/licenses/mit-license.php

import os
import sys
import logging

from rhev import *
from urllib import urlretrieve
from optparse import OptionParser
from getpass import getpass

def setup_logging(debug):
    """Set up logging."""
    if debug:
        level = logging.DEBUG
    else:
        level = logging.INFO
    logger = logging.getLogger()
    handler = logging.StreamHandler(sys.stdout)
    format = '%(levelname)s: %(message)s'
    formatter = logging.Formatter(format)
    handler.setFormatter(formatter)
    logger.addHandler(handler)
    logger.setLevel(level)
    
def parse_args():
    """Parse command-line arguments."""
    parser = OptionParser(usage='%prog [options] <name>',
                          description='create a VM using the SDK')
    parser.add_option('-U', '--url', help='the API entry point URL')
    parser.add_option('-u', '--username', help='the user to connect as')
    #parser.add_option('-p', '--password', help='the user\'s password')
    parser.add_option('-d', '--debug', action="store_true", help='enable debugging')
    for key in ('url', 'username'):
        name = 'RHEV_%s' % key.upper()
        parser.set_default(key, os.environ.get(name))
    opts, args = parser.parse_args()
    for key in ('url', 'username'):
        if getattr(opts, key) is None:
            name = 'RHEV_%s' % key.upper()
            parser.error('please specify --%s or set $%s' % (key, name))
    if len(args) != 1:
        parser.print_usage()
        parser.exit()
    return opts, args
    
opts, args = parse_args()

if opts.debug is not None:
    setup_logging(True)

try:
    password = getpass('%s password: ' % opts.username)
    api = Connection(opts.url, opts.username, password)

    vm = api.get(schema.VM, name=args[0])
    if vm is None:
        raise Error('vm "%s" not found' % args[0])
    if vm.status != "UP":
        raise Error('vm "%s" is not UP' % args[0])
    action_in = schema.new(schema.Action)
    action_in.expiry = 120
    action = api.action(vm, 'ticket', action_in)
    #print 'ticket value: %s, action status: %s' % (action.ticket.value_, action.status)

    securePort = 5890 - (vm.display.port - 5910)
    
    # RHEL6, spice 0.6
    if os.path.isfile('/usr/bin/spicec'):
        cert_file = os.environ['HOME'] + '/.spicec/spice_truststore.pem'
        spicec = '/usr/bin/spicec -h %s -p %s -s %s -w %s' \
                % (vm.display.address, vm.display.port, securePort, action.ticket.value_)
    # RHEL5, spice 0.3
    elif os.path.isfile('/usr/libexec/spicec'):
        cert_file = os.environ['HOME'] + '/.spicec/spice_truststore.pem'
        spicec = '/usr/libexec/spicec %s %s %s --ssl-channels smain,sinputs --ca-file %s -p %s' \
                % (vm.display.address, vm.display.port, securePort, cert_file, action.ticket.value_)
    # Windows, spice 0.3
    elif os.path.isfile('C:\Program Files (x86)\Redhat\RHEV\SpiceClient\spicec.exe'):
        cert_file = os.environ['APPDATA'] + '/spice/spice_truststore.pem'
        spicec = 'C:\Program Files (x86)\Redhat\RHEV\SpiceClient\spicec.exe %s %s %s --ssl-channels smain,sinputs --ca-file %s -p %s' \
                % (vm.display.address, vm.display.port, securePort, cert_file, action.ticket.value_)
    else:
        raise Error('could not find spice client executable')
    
    # CA cert url available at https://rhevm.example.com/ca.crt
    [cert_url, tmp] = opts.url.rsplit(':', 1);
    cert_url += '/ca.crt'
    urlretrieve(cert_url, cert_file);

    os.system(spicec)
except Error, e:
    sys.stderr.write('error: %s\n' % str(e))
    sys.exit(1)
