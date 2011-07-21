#!/usr/bin/env python
# encoding: utf-8
"""
Fingerprint a file and submit to Echonest to return artist and title 
(tab-separated)
Save Echonest's response Json alongside the input with a .en.json extension

Originally created by Ben Fields on 2010-10-19.
Copyright (c) 2010 Goldsmiths University of London. All rights reserved.

Munged into Echonest only by Bart Nagel <bjn@ecs.soton.ac.uk>
"""

import sys
import subprocess
import simplejson as json
import urllib2
import os
import tempfile
import string

from medialength import medialength

API_KEY = "1WJE4HXGNDRCZ7MMK" # use your API key here (or mine as long as you're considerate...)
ENMFP = os.path.dirname(os.path.dirname(os.path.abspath(__file__))) + "/lib/codegen-3.15/codegen.Linux-x86_64" # path to the binary here

def main():
	if len(sys.argv) != 2:
		print >>sys.stderr, "Usage: \n>echonest.py <audioFile>"
		sys.exit(255)

	if not os.path.exists(sys.argv[1]):
		print >>sys.stderr, "file '%s' doesn't exist" % sys.argv[1]
		sys.exit(255)

	length = medialength(sys.argv[1])

	if length <= 90:
		print >>sys.stderr, "song is 90 seconds or less -- fingerprinting entire song"
		query_text = fingerprint(sys.argv[1])
		en_response = queryechonest(query_text)
	else:
		print >>sys.stderr, "song is over 90 seconds -- fingerprinting for one minute from 30s"
		query_text = fingerprint(sys.argv[1], 30, 60)
		en_response = queryechonest(query_text)
		if len(en_response['response']['songs']) == 0:
			print >>sys.stderr, "no matches -- fingerprinting for two minutes from 30s"
			query_text = fingerprint(sys.argv[1], 30, 120)
			en_response = queryechonest(query_text)
			if len(en_response['response']['songs']) == 0:
				print >>sys.stderr, "no matches -- fingerprinting from start for 5 minutes"
				query_text = fingerprint(sys.argv[1], 0, 300)
				en_response = queryechonest(query_text)

	outputfilename = sys.argv[1] + ".en.json"
	print >>sys.stderr, "writing raw echonest output to '%s'" % outputfilename
	outputfile = open(outputfilename, "w")
	outputfile.write(query_text)
	outputfile.close()

	if len(en_response['response']['songs']) == 0:
		print >>sys.stderr, "Echonest gave no matches"
		sys.exit(1)

	# output the artist and title, tab-separated
	print en_response["response"]["songs"][0]["artist_name"].encode("utf-8") + "\t" + en_response["response"]["songs"][0]["title"].encode("utf-8")

	sys.exit(0)

def fingerprint(path, start = None, duration = None):
	args = [ENMFP, path]
	if start is not None or duration is not None:
		if start is None:
			args.append("0")
		else:
			args.append(str(start))
		if duration is not None:
			args.append(str(duration))

	enmfp_proc = subprocess.Popen(args, stdout=subprocess.PIPE)
	enmfp_proc.wait()
	query_text = enmfp_proc.communicate()[0] #stderr should go to stderr, so we only grab stdout with communicate
	enmfp_proc.stdout.close()

	print >>sys.stderr, "enmfp's response:"
	print >>sys.stderr, query_text

	if enmfp_proc.returncode == -6:
		print >>sys.stderr, "enmfp exited with signal 6 (it aborted). trying to normalize the track and re-fingerprint"

		# make fifo for audio
		tmpdir = tempfile.mkdtemp()
		fifopath = os.path.join(tmpdir, "fifo")
		try:
			os.mkfifo(fifopath)
		except OSError, e:
			print >>sys.stderr, "failed to create fifo: %s" % e
			sys.exit(14)

		# get boost amount
		sox_scan = subprocess.Popen(["sox", path, "-t", "wav", "/dev/null", "stat", "-v"], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
		sox_scan.wait()
		sox_scan_out = sox_scan.communicate()
		sox_scan.stdout.close()
		sox_scan.stderr.close()
		if sox_scan.returncode != 0:
			print >>sys.stderr, "sox (stat) exited with non-zero code %d" % sox_scan.returncode
			sys.exit(14)
		for line in string.split(sox_scan_out[1], "\n"):
			if line[0:4] == "sox:" or len(line) == 0:
				continue
			boost = line

		# boost and put through fifo to enmfp, wait for enmfp to exit
		args[1] = fifopath
		enmfp_proc = subprocess.Popen(args, stdout=subprocess.PIPE)
		sox_boost = subprocess.Popen(["sox", path, "-v", boost, "-t", "wav", fifopath])
		enmfp_proc.wait()

		# sox should have exited by now. if not something may be wrong
		if sox_boost.returncode is None:
			print >>sys.stderr, "sox (boost) is still running even though enmfp has exited. something may be wrong. killing it."
			sox_boost.kill()
		elif sox_boost.returncode != 0:
			print >>sys.stderr, "sox (boost) exited with non-zero code %d" % sox_boost.returncode
			sys.exit(15)

		# get enmfp's stdout
		query_text = enmfp_proc.communicate()[0]
		enmfp_proc.stdout.close()
		print >>sys.stderr, "enmfp's new response:"
		print >>sys.stderr, query_text

		# remove fifo and its dir
		os.remove(fifopath)
		os.rmdir(tmpdir)

		if enmfp_proc.returncode < 0:
			print >>sys.stderr, "enmfp was terminated by signal %d" % -enmfp_proc.returncode
			sys.exit(16)
		elif enmfp_proc.returncode != 1:
			print >>sys.stderr, "enmfp exited with code %d" % enmfp_proc.returncode
			sys.exit(17)
	elif enmfp_proc.returncode < 0:
		print >>sys.stderr, "enmfp was terminated by signal %d" % -enmfp_proc.returncode
		sys.exit(10)
	elif enmfp_proc.returncode != 1:
		# oddly, it seems to return 1 on success (and some failures)
		print >>sys.stderr, "enmfp exited with code %d" % enmfp_proc.returncode
		sys.exit(11)

	formed_out = json.loads(query_text)

	if formed_out[0] is None:
		print >>sys.stderr, "unexpected output from enmfp"
		sys.exit(12)
	if "error" in formed_out[0]:
		print >>sys.stderr, "got error message from enmfp: '%s'" % formed_out[0]["error"]
		sys.exit(13)

	return query_text

def queryechonest(query_text):
	try:
		handler = urllib2.urlopen("http://developer.echonest.com/api/v4/song/identify?api_key="+API_KEY, data="query="+query_text)
		outdata = handler.read()
	except urllib2.HTTPError, e:
		print >>sys.stderr, "HTTPError:", e
		sys.exit(8)
	except Exception, e:
		print >>sys.stderr, "Got exception:", type(e), e
		sys.exit(9)

	print >>sys.stderr, "raw data:\n"+outdata+"\ngathering id..."
	return json.loads(outdata)

if __name__ == '__main__':
	main()
