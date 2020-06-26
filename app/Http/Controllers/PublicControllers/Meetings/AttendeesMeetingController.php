<?php

namespace App\Http\Controllers\PublicControllers\Meetings;

use App\bigbluebutton\src\Parameters\CreateMeetingParameters;
use App\Helpers\bbbHelpers;
use App\Helpers\Helper;
use App\Http\Controllers\Admin\MeetingController;
use App\Http\Controllers\Controller;
use App\Meeting;
use BigBlueButton\BigBlueButton;
use BigBlueButton\Parameters\GetMeetingInfoParameters;
use BigBlueButton\Parameters\IsMeetingRunningParameters;
use BigBlueButton\Parameters\JoinMeetingParameters;
use http\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class  AttendeesMeetingController extends Controller
{
    //

    protected  $logoutUrl ='/meetings';
    public function accessCodeResult(Request $request)
    {

        $validator = Validator::make($request->all(),[
            'access_code' =>'required'
        ],[
            'access_code.required' =>'Please Enter Access Code'
        ]);

        if ($validator->fails())
        {
            return response()->json(['error'=>$validator->errors()->all()]);
        }
        $room = Meeting::where('url',decrypt($request->input('room')))
            ->where('access_code',$request->input('access_code'))
            ->first();
        if (!empty($room))
        {
            $recordingList = bbbHelpers::recordingLists(decrypt($request->input('room')));
            return view('includes.meetingJoinForm',compact('room','recordingList'));

        }
        else
        {
            $room = Meeting::where('url',decrypt($request->input('room')))
                ->firstOrFail();
            return view('includes.meetingAccessCodeForm',compact('room'));

        }
    }
    public function joinMeetingAttendee(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' =>'required'
        ],[
            'name.required' =>'Name Required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['error'=>$validator->errors()->all()]);
        }



        $room = Meeting::where('url',decrypt($request->input('room')))->firstOrFail();
        $credentials = bbbHelpers::setCredentials();
        $bbb = new BigBlueButton($credentials['base_url'],$credentials['secret']);
        $ismeetingRunningParams =  new IsMeetingRunningParameters(decrypt($request->input('room')));
        $response =$bbb->isMeetingRunning($ismeetingRunningParams);

        if ($response->getRawXml()->running == 'false')
        {
            return response()->json(['notStart'=>true]);
        }
        else{
            $joinMeetingParams = [

                'meetingId'  => decrypt($request->input('room')),
                'username'   => $request->input('name'),
                'password'   => decrypt($room->attendee_password)
            ];
           $url = bbbHelpers::joinMeeting($joinMeetingParams);
           return response()->json(['url'=>$url]);


        }


    }

    public function attendeeStartRoom(Request $request)
    {

        $meeting = Meeting::where('url',decrypt($request->input('room')))
            ->firstOrFail();

        $this->logoutUrl = url($this->logoutUrl.'/'.$meeting->url);

        $meetingsParams = [

            'meetingUrl' => decrypt($request->input('room')),
            'meetingName' => $meeting->name,
            'attendeePassword' => decrypt($meeting->attendee_password),
            'moderatorPassword' => $meeting->user->password,
            'muteAllUser' => $meeting->mute_on_join,
            'logoutUrl' => $this->logoutUrl,
            'setRecord' => true,


        ];

            $response = bbbHelpers::createMeeting($meetingsParams);
            $joinMeetingParams = [
                    'meetingId'  => decrypt($request->input('room')),
                    'username'   => $request->input('name'),
                    'password'   => decrypt($meeting->attendee_password)
                ];

            $apiUrl = bbbHelpers::joinMeeting($joinMeetingParams);
            return redirect()->to($apiUrl);

    }
    public function attendeeJoinAsModerator(Request $request)
    {


        $meeting = Meeting::where('url',decrypt($request->input('room')))
            ->firstOrFail();


        $this->logoutUrl = url($this->logoutUrl.'/'.$meeting->url);

        $meetingsParams = [

            'meetingUrl' => decrypt($request->input('room')),
            'meetingName' => $meeting->name,
            'attendeePassword' => decrypt($meeting->attendee_password),
            'moderatorPassword' => $meeting->user->password,
            'muteAllUser' => $meeting->mute_on_join,
            'logoutUrl' =>  $this->logoutUrl,
            'setRecord' => true,
        ];
        $response  = bbbHelpers::createMeeting($meetingsParams);
        $joinMeetingParams = [

            'meetingId'  => decrypt($request->input('room')),
            'username'   => $request->input('name'),
            'password'   => $meeting->user->password
        ];

        $apiUrl = bbbHelpers::joinMeeting($joinMeetingParams);
        return redirect()->to($apiUrl);


    }
}
