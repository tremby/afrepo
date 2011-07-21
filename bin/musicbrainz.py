#!/usr/bin/env python
# encoding: utf-8
"""
Get the Musicbrainz ID of a track with a given artist and title

python musicbrainz is available via:

svn co http://svn.musicbrainz.org/python-musicbrainz2/trunk python-musicbrainz2

Created by Ben Fields on 2010-10-19.
Copyright (c) 2010 Goldsmiths University of London. All rights reserved.

Munged into Musicbrainz only by Bart Nagel <bjn@ecs.soton.ac.uk>
"""

import sys
from musicbrainz2.webservice import Query, TrackFilter, WebServiceError

def main():
	if len(sys.argv) != 3:
		print >> sys.stderr, "Usage: \n>musicbrainz.py <artist> <title>"
		sys.exit(255)

	q = Query()
	try:
		f = TrackFilter(artistName=sys.argv[1].decode("utf-8"), title=sys.argv[2].decode("utf-8"))
		results = q.getTracks(f)
	except WebServiceError, e:
		print >> sys.stderr, 'WebServiceError:', e
		sys.exit(8)
	except Exception, e:
		print >> sys.stderr, 'Got exception:', type(e), e
		sys.exit(9)

	if len(results) == 0:
		print >> sys.stderr, "No results from Musicbrainz"
		sys.exit(1)

	#print >> sys.stderr, "artist\ttitle\tscore\tid"
	for row in results:
		track = row.track
		print track.artist.name.encode("utf-8") + "\t" + track.title.encode("utf-8") + "\t" + str(row.score) + "\t" + track.id.encode("utf-8")

	sys.exit(0)

if __name__ == '__main__':
	main()
