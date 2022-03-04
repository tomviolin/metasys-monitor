while true; do
	changes=`git status --porcelain|wc -l`
	if [ "$changes" -gt "0" ]; then
		git add .
		git commit -m "robustifying the loading popup"
		git push
		sleep 5
	fi
	sleep 0.5
done

