#!/bin/bash

# Let's start Oracle in the background, using nohup.
#nohup ./entrypoint.sh &> nohup.out&

echo "echo \"Database ready to use. Enjoy! ;)\"" >> /usr/sbin/startup.sh

nohup /usr/sbin/startup.sh &> nohup.out&

#  check to see if it started correctly
TIMER=0
ORACLE_RESULT="unknown"
while true; do
  if [[ $ORACLE_RESULT != "unknown" ]]; then
    break
  fi
  sleep 1
  TIMER=$((TIMER + 1))

  if [[ $TIMER == "300" ]]; then
    ORACLE_RESULT="failed"
  fi

  # read in the file containing the std out of the pppd command
  #  and look for the lines that tell us what happened
  while read line; do
    echo $line
    if [[ $line == "Database ready to use. Enjoy! ;)" ]]; then
      echo "Oracle successfully started"
      ORACLE_RESULT="success"
      break
#    elif [[ $line == *is\ locked\ by\ pid* ]]; then
#      echo "pppd is already running and has locked the serial port."
#      ORACLE_RESULT="running"
#      break;
    fi
  done < <( cat ./nohup.out )
done

if [[ $ORACLE_RESULT == "success" ]]; then
    echo "Now, let's continue"

    cd /app
    # Let's execute the command
    sh -c "$*"
else
    echo "Oracle failed to start";
fi
