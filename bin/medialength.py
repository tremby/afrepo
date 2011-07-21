#!/usr/bin/env python

import sys
import os
import subprocess
import re

# call ffmpeg to determine track length, no matter what format it is
# return the length in seconds
def medialength(path):
	if not os.path.exists(path):
		print >>sys.stderr, "file '%s' doesn't exist" % path
		return None
	ffmpeg = subprocess.Popen(["ffmpeg", "-i", path], stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
	if ffmpeg.wait() != 1:
		print >>sys.stderr, "ffmpeg exited with code %d (should be 1 since no output file is specified) when trying to determine length of file '%s'" % (ffmpeg.returncode, path)
		return None
	# read the stdout
	output = ffmpeg.communicate()[0]
	ffmpeg.stdout.close()
	l = re.search('.*Duration: (..):(..):(..)\.(..).*', output)
	if not l:
		print >>sys.stderr, "ffmpeg didn't return a duration for file '%s'" % path
		return None
	return float(l.group(4)) / 100 + int(l.group(3)) + int(l.group(2)) * 60 + int(l.group(1)) * 60 * 60;

if __name__ == "__main__":
	if len(sys.argv) != 2:
		print >>sys.stderr, "expected a path"
		sys.exit(255)
	length = medialength(sys.argv[1])
	if length is None:
		sys.exit(1)
	print length
	sys.exit(0)
