while true; do
	changes=`git status --porcelain|wc -l`
	if [ "$changes" -gt "0" ]; then
		git add .
		git commit -m "csv file export"
		git push
		sleep 5
		php check_pipeline.php
	fi
	sleep 0.5
done

