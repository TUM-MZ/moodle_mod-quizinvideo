# moodle_mod-quizinvideo
## About
This module will let users create quiz which can be showed during a video. 
Supported video formats are HTML5 videos, RTMP videos, Youtube viedos.
## Installation
Clone this repository in /mod/ folder. 
This module also requires users to install [moodle-qbehaviour_quizinvideo](https://github.com/TUM-MZ/moodle-qbehaviour_quizinvideo). 
To show RTMP urls properly, rtmp domains should be entered in plugin settings for proper RTMP url parsing. 
## Usage
Create a quizinvideo from a course, give name of the quizinvideo, add url and adjust parameters according to individual needs. 
We suggest to check all options in Review Options.
After adding the quizinvideo, go to edit quizinvideo and add questions. 
Pages will be showed during quizinvideo one at a time.
While the video is playing, you can manually enter time of video for a page or copy current timestamp from video.
After providing time for every page, the quizinvideo is ready.
