#!/bin/sh
if [ $# -eq 0 ]; then
	echo "Usage: $(basename $0) <php-class> <logfile> <dir> [<dir>...]" >&2
	exit 255
fi

SCRIPT="$0"
PHPCLASS="$1"
shift
LOGFILE="$1"
shift

n=0
total=$(find "$@" -type f | wc -l)
for dir in "$@"; do
	for file in "$dir"/*; do
		if [ ! -L "$file" ]; then
			continue
		fi

		canonfile=$(readlink -e "$file")
		let n=n+1

		echo "file $n: '$file', link to '$canonfile'" >&2
		ls -lh "$file" >&2

		# skip if it's not the preferred file for its particular song
		preferred=$(php $(dirname $0)/filetopreferredfile.php "$PHPCLASS" "$file")
		preferredstatus=$?
		if [ $preferredstatus -ne 0 ]; then
			echo "got a non-successful error status ($preferredstatus) when trying to find preferred file" >&2
			continue
		fi
		if [ "$preferred" != "$canonfile" ]; then
			echo "this file is not the preferred file for the song, skipping" >&2
			continue
		fi

		# check if this one's already been done
		while [ -d lock ]; do
			echo "locked..." >&2
			sleep 1
		done
		grepout=$(grep "^$canonfile	" "$LOGFILE")
		if [ $? -eq 0 ]; then
			if echo "$grepout" | grep -qv "	ERROR	"; then
				echo "skipping file previously completed with no errors" >&2
				continue
			fi

			# working on a previously attempted file -- delete its line from the 
			# log first
			while [ -d lock ]; do
				echo "locked..." >&2
				sleep 1
			done
			mkdir lock
			sed -i -r "/^$(echo "$canonfile" | sed -r 's%/%\\/%g')	/d" "$LOGFILE"
			rmdir lock
		fi

		# path
		output="$canonfile"

		# run Echonest fingerprinter
		attempts=0
		while true; do
			if [ $attempts -ge 5 ]; then
				echo "aborting after $attempts attempts" >&2
				output="${output}\tERROR\t\t"
				break;
			fi
			let attempts=attempts+1

			enout=$(python "$(dirname $0)"/echonest.py "$file")
			enstatus=$?
			if [ $enstatus -eq 0 ]; then
				output="${output}\tHIT\t$enout"
				break
			elif [ $enstatus -eq 1 ]; then
				output="${output}\tMISS\t\t"
				break
			elif [ $enstatus -eq 8 ]; then
				echo "HTTP error, waiting 3 seconds and trying again" >&2
				sleep 3
				continue
			else
				output="${output}\tERROR\t\t"
				break
			fi
		done

		# if Echonest gave a result, pass that to Musicbrainz
		if [ $enstatus -eq 0 ]; then
			enartist=$(echo "$enout" | cut -f 1)
			entitle=$(echo "$enout" | cut -f 2)

			attempts=0
			while true; do
				if [ $attempts -ge 5 ]; then
					echo "aborting after $attempts attempts" >&2
					output="${output}\tERROR\t\t"
					break;
				fi
				let attempts=attempts+1

				enmbout=$(python "$(dirname $0)"/musicbrainz.py "$enartist" "$entitle")
				enmbstatus=$?
				if [ $enmbstatus -eq 0 ]; then
					enmbartist=$(echo "$enmbout" | head -n 1 | cut -f 1)
					enmbtitle=$(echo "$enmbout" | head -n 1 | cut -f 2)
					enmbid=$(echo "$enmbout" | head -n 1 | cut -f 4)
					output="${output}\tHIT\t${enmbartist}\t${enmbtitle}\t${enmbid}"
					break
				elif [ $enmbstatus -eq 1 ]; then
					output="${output}\tMISS\t\t\t"
					break
				elif [ $enmbstatus -eq 8 ]; then
					echo "HTTP error, waiting 3 seconds and trying again" >&2
					sleep 3
					continue
				else
					output="${output}\tERROR\t\t\t"
					break
				fi
			done
		else
			output="${output}\tNA\t\t\t"
		fi

		# see what the database has to offer
		dbout=$($(dirname "$SCRIPT")/filetometadata.php "$PHPCLASS" "$file")
		dbstatus=$?
		if [ $dbstatus -eq 0 ]; then
			output="${output}\tHIT\t${dbout}"
		elif [ $dbstatus -eq 1 ]; then
			output="${output}\tMISS\t\t"
		else
			output="${output}\tERROR\t\t"
		fi

		# if the DB gave a result, pass that to Musicbrainz
		if [ $dbstatus -eq 0 ]; then
			dbartist=$(echo "$dbout" | cut -f 1)
			dbtitle=$(echo "$dbout" | cut -f 2)

			attempts=0
			while true; do
				if [ $attempts -ge 5 ]; then
					echo "aborting after $attempts attempts" >&2
					output="${output}\tERROR\t\t"
					break;
				fi
				let attempts=attempts+1

				dbmbout=$(python "$(dirname $0)"/musicbrainz.py "$dbartist" "$dbtitle")
				dbmbstatus=$?
				if [ $dbmbstatus -eq 0 ]; then
					dbmbartist=$(echo "$dbmbout" | head -n 1 | cut -f 1)
					dbmbtitle=$(echo "$dbmbout" | head -n 1 | cut -f 2)
					dbmbid=$(echo "$dbmbout" | head -n 1 | cut -f 4)
					output="${output}\tHIT\t${dbmbartist}\t${dbmbtitle}\t${dbmbid}"
					break
				elif [ $dbmbstatus -eq 1 ]; then
					output="${output}\tMISS\t\t\t"
					break
				elif [ $dbmbstatus -eq 8 ]; then
					echo "HTTP error, waiting 3 seconds and trying again" >&2
					sleep 3
					continue
				else
					output="${output}\tERROR\t\t\t"
					break
				fi
			done
		else
			output="${output}\tNA\t\t\t"
		fi

		while [ -d lock ]; do
			echo "locked..." >&2
			sleep 1
		done
		mkdir lock
		echo -e "$output" >>"$LOGFILE"
		rmdir lock
		echo -e "$output"
	done
done
