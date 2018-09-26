<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Validator;
use Hash;
use Illuminate\Validation\Rule;
use App\Entry;
use App\User;
use App\Topic;
use App\EntryTopic;
use App\Helpers;
use App\Uploadedfile;
use App\UserTranscription;
use App\Rights;
use DB;
use App\Pages as Pages;
use App\EntryFormats as EntryFormats;
use Illuminate\Http\Request;
use App\Http\Controllers\FileEntryController;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;


class ApiIngestionController extends Controller
{

    use Helpers\PrepareOutputTrait;


    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {

        if (Entry::first() != null) {
            $sort = "asc";
            $paginate = 10;
            if ($request->input('sort') !== "") {
                $sort = $request->input('sort');
            }
            if ($request->input('paginate') !== "") {
                $paginate = $request->input('paginate');
            }
            $coll = Entry::where('current_version', 1)->orderBy('created_at', $sort)->paginate($paginate);
        } else {
            $coll = "empty bottle";
        };

        return $this->prepareResult(true, $coll, [], "All user entries");

    }

    public function indexAll(Request $request)
    {

        if (Entry::first() != null) {
            $sort = "asc";
            if ($request->input('sort') !== "") {
                $sort = $request->input('sort');
            }
            $coll = Entry::where('current_version', 1)->orderBy('created_at', $sort)->get();
        } else {
            $coll = "empty bottle";
        };

        return $this->prepareResult(true, $coll, [], "All user entries");

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Entry $entry
     * @return \Illuminate\Http\Response
     */

    public function show(Request $request, $id)
    {
        $user = null;
        if ( $request->has('Authorization') || $request->header('Authorization') ) {
            $user = Auth::guard('api')->user();
        }
        if ($user!==null){
          if ($user->isAdmin()) {
            $entry = Entry::where('id', $id)->first();
          }
          else {
            $entry = Entry::where([
              ['id','=', $id],
              ['current_version','=',1],
              ['status','=',1],
              ['transcription_status','=',2],
            ])->first();
          }
        }
        else {
          $entry = Entry::where([
            ['id','=', $id],
            ['current_version','=',1],
            ['status','=',1],
            ['transcription_status','=',2],
          ])->first();
        }
        $msg = "Entry found";
        $status = true;


        if ($entry !== null) {
            $coll = json_decode($entry->element, true);
        } else {
            $coll = "";
            $msg = "Entry not found";
            $status = false;

            return $this->prepareResult($status, $coll, [], $msg, "Request complete");

        };

        return $this->prepareResult($status, $coll, [], $msg, "Request complete");

    }

    public function showLetter(Request $request, $id)
    {
        $user = null;
        if ( $request->has('Authorization') || $request->header('Authorization') ) {
            $user = Auth::guard('api')->user();
        }
        if ($user!==null){
          if ($user->isAdmin()) {
            $entry = DB::table('entry')
              ->join('uploadedfile', 'entry.uploadedfile_id','=','uploadedfile.id')
              ->select('entry.*')
              ->where('uploadedfile.original_filename',$id.".xml")
              ->where('entry.current_version','1')
              ->first();
          }
          else {
            $entry = DB::table('entry')
              ->join('uploadedfile', 'entry.uploadedfile_id','=','uploadedfile.id')
              ->select('entry.*')
              ->where([
                ['uploadedfile.original_filename',$id.".xml"],
                ['current_version','=',1],
                ['status','=',1],
                ['transcription_status','=',2]
              ]
                )
              ->where('entry.current_version','1')
              ->first();
          }
        }
        else {
          $entry = DB::table('entry')
            ->join('uploadedfile', 'entry.uploadedfile_id','=','uploadedfile.id')
            ->select('entry.*')
            ->where([
              ['uploadedfile.original_filename',$id.".xml"],
              ['current_version','=',1],
              ['status','=',1],
              ['transcription_status','=',2]
            ]
              )
            ->where('entry.current_version','1')
            ->first();
        }



        $msg = "Entry found";
        $status = true;


        if ($entry != null) {
            $coll = json_decode($entry->element, true);

        } else {
            $coll = "";
            $msg = "Entry not found";
            $status = false;

            return $this->prepareResult($status, $coll, [], $msg, "Request complete");
        };

        return $this->prepareResult($status, $coll, [], $msg, "Request complete");

    }

    public function store(Request $request)
    {
        $error = false;
        $format = $request->input('format');
        $data = $request->input('data');

        $entry_format = EntryFormats\Factory::create($format);
        $data_json = json_decode($data,true);

        $validator = $entry_format->valid($data_json);

        if ($validator->fails()) {

            $error = true;
            $errors['document_id'] = $data_json['document_id'];
            $errors['errors'] = $validator->errors();

            return $this->prepareResult(false, $errors, $error['errors'], "Error in creating entry");

        }
        else {
            $document_id = intval($data_json['document_id']);
            $entryPages = $data_json['pages'];
            $transcription_status = intval($data_json['transcription_status']);
            $status = intval($data_json['status']);
            if ($transcription_status===0 && $status===0) {
              $transcription_status = -1;
            }

            // check if the entry already exists in the db
            $existing_entry = Entry::where('element->document_id', intval($document_id))->first();
            $existing_element = json_decode($existing_entry['element'], true);
            $existing_modified_timestamp = $existing_element['modified_timestamp'];

            if ($existing_entry!==null && $existing_modified_timestamp===$data_json['modified_timestamp']) {
              return $this->prepareResult(true, [], $error['errors'], "Entry already inserted");
            }
            else {
              $current_version = 1;

              if ($existing_entry!==null && $existing_entry->current_version===1) {
                $current_version = 0;
              }
              $entry = new Entry();
              $entry->element = json_encode($data_json);
              $entry->user_id = Auth::user()->id;
              $entry->current_version = $current_version;
              $entry->status = $this->getEntryStatus($entryPages);
              $entry->transcription_status = $transcription_status;
              $entry->notes = "";

              $entry->save();

              return $this->prepareResult(true, [], $error['errors'], "Entry created");
            }

        }

    }

    public function missedFilesPatch(Request $request)
    {

        $error = false;
        $format = "uploader";
        $data = $request->input('data');

        $entry_format = EntryFormats\Factory::create($format);
        $data_json = json_decode($data,true);
        $document_id = intval($data_json['document_id']);

        $validator = $entry_format->valid($data_json);

        if ($validator->fails()) {

            $error = true;
            $errors['document_id'] = $data_json['document_id'];
            $errors['errors'] = $validator->errors();

            return $this->prepareResult(false, $errors, $error['errors'], "Error in creating entry");
        }
        else {
            $response = array();
            $document_id = intval($data_json['document_id']);
            $entryPages = $data_json['pages'];
            $transcription_status = intval($data_json['transcription_status']);
            $status = intval($data_json['status']);
            if ($transcription_status===0 && $status===0) {
              $transcription_status = -1;
            }

            // check if the entry already exists in the db and update if pages are empty
            $existing_entries = Entry::where('element->document_id', intval($document_id))->get();
            if (!empty($existing_entries) && $existing_entries!==null) {
              foreach ($existing_entries as $existing_entry) {
                $existing_element = json_decode($existing_entry['element'], true);
                $existing_pages = $existing_element['pages'];
                if (empty($existing_pages)) {
                  $existing_element['pages']=$entryPages;
                  $existing_entry->element=json_encode($existing_element);
                  $existing_entry->save();

                  $response[] = $existing_entry->id." pages updated successfully";
                }
              }
            }
            else if (count($existing_entries)===0){
              $current_version = 1;

              $entry = new Entry();
              $entry->element = json_encode($data_json);
              $entry->user_id = Auth::user()->id;
              $entry->current_version = $current_version;
              $entry->status = $this->getEntryStatus($entryPages);
              $entry->transcription_status = $transcription_status;
              $entry->notes = "";

              $entry->save();

              $response[] = "New entry for Omeka document with id:".$document_id." created successfully";
            }
            return $this->prepareResult(true, $response, $error['errors'], $document_id);
        }

    }

    public function testAPI() {
        $document_ids = array(109, 347, 458, 722, 813, 1015, 1016, 1017, 1060, 1374, 1382, 1386, 1389, 1404, 1405, 1995, 1996, 1997, 1998, 2020, 2024, 2082, 2086, 2137, 2138, 2193, 2218, 2259, 2391, 2466, 2467, 2470, 2473, 2474, 2483, 2535, 2536, 2537, 2538, 2548, 2551, 2603, 2604, 2605, 2606, 2607, 2609, 2610, 2612, 2613, 2614, 2615, 2616, 2618, 2619, 2621, 2622, 2623, 2625, 2626, 2627, 2630, 2640, 2641, 2642, 2643, 2644, 2645, 2646, 2647, 2648, 2650, 2656, 2666, 2667, 2668, 2669, 2671, 2672, 2675, 2677, 2681, 2682, 2685, 2690, 2691, 2692, 2693, 2694, 2695, 2696, 2698, 2707, 2713, 2714, 2717, 2725, 2726, 2727, 2728, 2749, 2751, 2752, 2753, 2756, 2906, 2907, 2920, 2921, 2924, 2925, 2926, 2927, 2931, 2933, 2951, 2954, 2957, 2958, 2959, 2982, 2986, 2987, 2988, 2989, 2991, 2993, 3094, 3095, 3339, 3341, 3345, 3347, 3349, 3351, 3357, 3359, 3362, 3363, 3364, 3366, 3372, 3373, 3380, 3381, 3385, 3386, 3387, 3388, 3389, 3391, 3397, 3398, 3399, 3405, 3406, 3407, 3408, 3409, 3410, 3411, 3412, 3413, 3414, 3415, 3416, 3417, 3418, 3419, 3420, 3421, 3423, 3431, 3432, 3433, 3434, 3435, 3436, 3437, 3440, 3699, 3701, 3705, 3901, 3948, 3988, 3999, 4030, 4031, 4067, 4068, 4069, 4070, 4071, 4072, 4073, 4074, 4075, 4076, 4077, 4078, 4079, 4080, 4081, 4082, 4083, 4084, 4085, 4086, 4087, 4088, 4089, 4090, 4091, 4092, 4093, 4094, 4097, 4098, 4099, 4100, 4101, 4102, 4103, 4104, 4105, 4106, 4108, 4109, 4110, 4111, 4112, 4113, 4114, 4115, 4116, 4117, 4118, 4119, 4120, 4121, 4122, 4123, 4125, 4126, 4127, 4128, 4129, 4130, 4131, 4132, 4133, 4134, 4135, 4136, 4137, 4138, 4139, 4140, 4141, 4142, 4143, 4144, 4145, 4146, 4147, 4148, 4149, 4150, 4151, 4153, 4154, 4155, 4156, 4157, 4158, 4159, 4160, 4161, 4162, 4163, 4164, 4165, 4166, 4167, 4168, 4169, 4170, 4171, 4172, 4173, 4174, 4175, 4176, 4177, 4179, 4180, 4181, 4182, 4183, 4184, 4185, 4186, 4187, 4188, 4189, 4190, 4191, 4192, 4193, 4194, 4195, 4196, 4197, 4198, 4199, 4200, 4201, 4202, 4203, 4204, 4205, 4206, 4207, 4208, 4209, 4210, 4211, 4212, 4213, 4214, 4215, 4216, 4217, 4218, 4219, 4220, 4222, 4223, 4224, 4225, 4226, 4227, 4228, 4229, 4230, 4231, 4233, 4234, 4235, 4236, 4237, 4238, 4239, 4240, 4241, 4242, 4243, 4244, 4245, 4246, 4247, 4248, 4249, 4251, 4252, 4253, 4254, 4255, 4256, 4257, 4258, 4259, 4260, 4261, 4262, 4263, 4264, 4265, 4266, 4267, 4268, 4269, 4270, 4271, 4272, 4273, 4274, 4275, 4276, 4277, 4278, 4279, 4280, 4281, 4282, 4283, 4284, 4285, 4286, 4287, 4288, 4289, 4290, 4291, 4292, 4293, 4294, 4295, 4296, 4297, 4298, 4299, 4300, 4301, 4302, 4303, 4304, 4305, 4306, 4307, 4308, 4309, 4310, 4311, 4312, 4313, 4314, 4315, 4316, 4317, 4318, 4319, 4320, 4322, 4323, 4325, 4326, 4327, 4328, 4329, 4330, 4331, 4332, 4333, 4334, 4335, 4336, 4337, 4339, 4340, 4341, 4342, 4344, 4345, 4346, 4348, 4349, 4350, 4351, 4352, 4353, 4354, 4355, 4356, 4357, 4358, 4359, 4360, 4361, 4362, 4363, 4364, 4365, 4366, 4367, 4368, 4369, 4370, 4371, 4372, 4373, 4374, 4375, 4376, 4377, 4378, 4379, 4381, 4383, 4384, 4385, 4386, 4388, 4389, 4390, 4393, 4394, 4395, 4398, 4400, 4401, 4402, 4405, 4406, 4407, 4409, 4410, 4413, 4418, 4419, 4420, 4421, 4422, 4424, 4425, 4426, 4427, 4433, 4437, 4442, 4443, 4444, 4445, 4462, 4467, 4496, 4513, 4514, 4532, 4537, 4542, 4544, 4545, 4554, 4555, 4717, 4725, 4729, 4739, 4744, 4748, 4751, 4760, 4765, 4779, 4788, 4818, 4820, 4821, 4822, 4823, 4824, 4825, 4828, 4831, 4832, 4836, 4839, 4840, 4841, 4843, 4844, 4845, 4846, 4847, 4848, 4849, 4850, 4851, 4852, 4853, 4854, 4855, 4856, 4858, 4859, 4860, 4862, 4864, 4865, 4867, 4868, 4869, 4872, 4873, 4874, 4875, 4876, 4878, 4879, 4883, 4885, 4886, 4887, 4888, 4889, 4891, 4892, 4893, 4895, 4896, 4897, 4898, 4899, 4900, 4908, 4909, 4910, 4911, 4912, 4913, 4914, 4915, 4916, 4917, 4918, 4919, 4920, 4921, 4922, 4926, 4927, 4928, 4929, 4930, 4931, 4935, 4936, 4937, 4938, 4939, 4940, 4941, 4942, 4943, 4944, 4945, 4946, 4947, 4948, 4949, 4950, 4951, 4953, 4954, 4955, 4956, 4957, 4958, 4959, 4960, 4961, 4962, 4964, 4965, 4966, 4967, 4968, 4969, 4970, 4983, 4988, 4990, 5001, 5002, 5003, 5004, 5011, 5012, 5013, 5014, 5015, 5016, 5020, 5021, 5024, 5026, 5031, 5032, 5033, 5034, 5035, 5036, 5037, 5038, 5039, 5040, 5041, 5042, 5043, 5044, 5045, 5046, 5047, 5048, 5049, 5050, 5051, 5052, 5053, 5054, 5055, 5056, 5057, 5058, 5059, 5060, 5061, 5062, 5063, 5064, 5065, 5066, 5067, 5068, 5069, 5070, 5071, 5072, 5073, 5074, 5076, 5078, 5079, 5080, 5081, 5082, 5083, 5084, 5085, 5086, 5087, 5088, 5089, 5090, 5091, 5092, 5093, 5094, 5095, 5096, 5097, 5098, 5099, 5100, 5101, 5102, 5103, 5104, 5105, 5106, 5107, 5108, 5109, 5110, 5112, 5113, 5114, 5115, 5116, 5117, 5118, 5119, 5120, 5121, 5122, 5123, 5124, 5125, 5126, 5127, 5128, 5129, 5130, 5131, 5132, 5133, 5134, 5135, 5136, 5137, 5138, 5139, 5140, 5141, 5142, 5143, 5144, 5145, 5146, 5147, 5148, 5149, 5150, 5151, 5152, 5153, 5154, 5155, 5156, 5157, 5158, 5159, 5160, 5161, 5162, 5163, 5164, 5165, 5166, 5167, 5168, 5169, 5170, 5171, 5172, 5173, 5174, 5175, 5176, 5177, 5178, 5179, 5180, 5181, 5182, 5183, 5184, 5185, 5186, 5187, 5188, 5189, 5190, 5191, 5192, 5193, 5194, 5195, 5196, 5197, 5198, 5199, 5200, 5201, 5202, 5203, 5204, 5205, 5206, 5207, 5208, 5209, 5210, 5211, 5212, 5213, 5214, 5215, 5216, 5217, 5218, 5219, 5220, 5221, 5222, 5223, 5224, 5225, 5226, 5227, 5228, 5229, 5231, 5232, 5234, 5235, 5236, 5246, 5247, 5248, 5249, 5250, 5251, 5252, 5253, 5254, 5255, 5256, 5277, 5278, 5279, 5280, 5281, 5305, 5306, 5307, 5310, 5312, 5313, 5314, 5315, 5316, 5317, 5319, 5329, 5333, 5344, 5345, 5346);

        $item_document_ids = array();
        $items = Entry::select('element->document_id as id')->whereIn('element->document_id',$document_ids)->get();
        foreach($items as $item) {
          $element = json_decode($item->element, true);
          $pages = $element['pages'];
          if (!empty($pages)) {
            $item_document_ids[]=intval($item['id']);
          }

        }

        $differences = array_diff($document_ids, $item_document_ids);
        $nottransferedIds = array();
        foreach($differences as $difference) {
          $nottransferedIds[]=$difference;
        }
        $new_entry = Entry::where('element->document_id', 5345)->get();
        $error = "none";
        if (count($new_entry)===0) {
          $error = "one";
        }
      return $this->prepareResult(true, $new_entry, $error, "All user entries");
    }

    public function uploadLetter(Request $request,$id) {
      $postData = $request->all();
      $formData = json_decode($postData['form']);
      $now = date("Y-m-d\TH:i:sP");
      $images = array();
      if (isset($postData['data'])) {
        $images = $postData['data'];
      }
      $images_types = $formData->additional_img_info;

      // topics
      $topics = array();
      $keywords = $formData->keywords;
      foreach ($keywords as $keyword) {
        $topic = array(
          "topic_id"=>$keyword->value,
          "topic_name"=>$keyword->label
        );
        $topics[] = $topic;
      }

      // date
      $date_created = (string)$formData->year;
      if (isset($formData->month) && $formData->month!=="") {
        $date_created .= "-".$formData->month;
      }
      if (isset($formData->day) && $formData->day!=="") {
        $date_created .= "-".$formData->day;
      }
      // source
      $source = "";
      if (gettype($formData->source)==="object") {
        $source = $formData->source->value;
      }
      else {
        $source = $formData->source;
      }
      // letter from
      $letter_from = "";
      if (gettype($formData->letter_from)==="object") {
        $letter_from = $formData->letter_from->value;
      }
      else {
        $letter_from = $formData->letter_from;
      }
      // letter to
      $letter_to = "";
      if (gettype($formData->letter_to)==="object") {
        $letter_to = $formData->letter_to->value;
      }
      else {
        $letter_to = $formData->letter_to;
      }

      $imgs_errors = array();
      if (intval($id)===0) {
        // pages
        $pages = array();
        $i=0;
        // uploaded files
        foreach($images as $image) {
          // upload image and store
          $fileEntryController = new FileEntryController();
          if ($fileEntryController->isImage($image)) {
            $extension=$image->getClientOriginalExtension();
            $filename = $image->getFilename().'.'.$extension;
            Storage::disk('fullsize')->put($filename, File::get($image));
            $fileEntryController->makeThumbnail($filename, 200);
            $saved_file = $fileEntryController->store($image, "uploader page", Auth::user()->id);
            $count = $i+1;

            $image_type = "Letter";
            if(isset($images_types[$i])) {
              $image_type = $images_types[$i];
            }
            $page = array(
              // logged in user id
              "rev_id"=>Auth::user()->id,
    		      "page_id"=> "",
              // logged in user name
    		      "rev_name"=> Auth::user()->name,
    		      "page_type"=> $image_type,
    		      "page_count"=> $count,
    		      "contributor"=> "",
    		      "transcription"=> "",
          		"archive_filename"=> $saved_file->filename,
          		"original_filename"=> $image->getClientOriginalName(),
          		"last_rev_timestamp"=> $now,
          		"transcription_status"=> "-1",
          		"doc_collection_identifier"=> ""
            );
            $pages[]=$page;
            $i++;
          }
          else {
            $imgs_errors[] = array("filename"=>$image->getClientOriginalName());
          }
        }

        $count=1;
        $newPages = array();
        foreach($pages as $page) {
          $page['page_count'] = $count;
          $newPages[]=$page;
          $count++;
        }

        $json_element = array(
          "type"=>"uploader",
  	      "debug"=>"",
  	      "pages"=> $newPages,
          "title"=> $formData->title,
        	"source"=> $source,
          "topics"=> $topics,
          "creator"=> $letter_from,
        	"creator_gender"=> $formData->creator_gender,
          "creator_location"=>$formData->creator_location,
          "user_id"=> Auth::user()->id,
          "language" => $formData->language,
          "recipient" => $letter_to,
          "time_zone"=> "Europe/Dublin",
          "collection"=> "",
          "api_version"=> "1.0",
          "description"=> $formData->additional_information,
          "document_id"=> $postData['letter_id'],
          "date_created"=>$date_created,
          "number_pages"=>count($images),
          "request_time"=>$now,
          "terms_of_use"=>$formData->terms_of_use,
          "collection_id"=>"",
          "doc_collection"=>$formData->doc_collection,
          "modified_timestamp"=>$now,
          "recipient_location"=>$formData->recipient_location,
          "copyright_statement"=>$formData->copyright_statement,
          "year_of_death_of_author"=>$formData->year_of_death_of_author,
        );
        $error = false;
        $format = "uploader";
        $entry_format = EntryFormats\Factory::create($format);
        $validator = $entry_format->valid($json_element);
        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();

            return $this->prepareResult(false, [$errors], $error['errors'], "Error in creating entry");
        }
        else {
            $entry = new Entry();
            $entry->element = json_encode($json_element);
            $entry->user_id = Auth::user()->id;
            $entry->current_version = TRUE;
            $entry->status = 0;
            $entry->transcription_status = 0;
            $entry->notes = $formData->notes;
            $entry->save();
            $error['files']=$imgs_errors;

            return $this->prepareResult(true, $entry, $error, "Entry created");
        }

      }
      else if (intval($id)>0) {
        // pages
        $pages = array();
        if (count($formData->pages)>0) {
          foreach($formData->pages as $newPage) {
            $newPage = (array)$newPage;
            $newPage['page_id']=0;
            $pages[] = $newPage;
          }
        }

        // uploaded files
        $i=0;
        foreach($images as $image) {
          // upload image and store

          $fileEntryController = new FileEntryController();
          if ($fileEntryController->isImage($image)) {
            $extension=$image->getClientOriginalExtension();
            $filename = $image->getFilename().'.'.$extension;
            Storage::disk('fullsize')->put($filename, File::get($image));
            $fileEntryController->makeThumbnail($filename, 200);
            $saved_file = $fileEntryController->store($image, "uploader page", Auth::user()->id);


            $image_type = "Letter";
            if(isset($images_types[$i])) {
              $image_type = $images_types[$i];
            }
            $page = array(
              // logged in user id
              "rev_id"=>Auth::user()->id,
    		      "page_id"=> "",
              // logged in user name
    		      "rev_name"=> Auth::user()->name,
    		      "page_type"=> $image_type,
    		      "contributor"=> "",
    		      "transcription"=> "",
          		"archive_filename"=> $saved_file->filename,
          		"original_filename"=> $image->getClientOriginalName(),
          		"last_rev_timestamp"=> $now,
          		"transcription_status"=> "0",
          		"doc_collection_identifier"=> ""
            );
            $pages[]=$page;
            $i++;
          }
          else {
            $imgs_errors[] = array("filename"=>$image->getClientOriginalName());
          }
        }
        $count=1;
        $newPages = array();
        foreach($pages as $page) {
          $page['page_count'] = $count;
          $newPages[]=$page;
          $count++;
        }

        $entry = Entry::where('id',$id)->first();
        $decode = json_decode($entry->element, true);
        $entry_type = $decode['type'];
        // element
        $json_element = array(
          "type"=>$entry_type,
  	      "debug"=>"",
  	      "pages"=> $newPages,
          "title"=> $formData->title,
        	"source"=> $source,
          "topics"=> $topics,
          "creator"=> $letter_from,
        	"creator_gender"=> $formData->creator_gender,
          "creator_location"=>$formData->creator_location,
          "user_id"=> Auth::user()->id,
          "language" => $formData->language,
          "recipient" => $letter_to,
          "time_zone"=> "Europe/Dublin",
          "collection"=> "",
          "api_version"=> "1.0",
          "description"=> $formData->additional_information,
          "document_id"=> $postData['letter_id'],
          "date_created"=>$date_created,
          "number_pages"=>$i,
          "request_time"=>$now,
          "terms_of_use"=>$formData->terms_of_use,
          "collection_id"=>"",
          "doc_collection"=>$formData->doc_collection,
          "modified_timestamp"=>$now,
          "recipient_location"=>$formData->recipient_location,
          "copyright_statement"=>$formData->copyright_statement,
          "year_of_death_of_author"=>$formData->year_of_death_of_author,
        );

        $error = false;
        $format = "uploader";
        $entry_format = EntryFormats\Factory::create($format);
        $validator = $entry_format->valid($json_element);
        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();

            return $this->prepareResult(false, $errors, $errors, "Error in updating entry");
        }
        else {

          $entry->element = json_encode($json_element);
          $entry->notes = $formData->notes;
          $entry->save();

          $json_element['notes'] = $formData->notes;
          $error['files']=$imgs_errors;
          return $this->prepareResult(true, $json_element, $error, "Entry updated successfully");
        }
      }

    }

    public function updatePagesOrder(Request $request, $id) {
      $error = array();
      $pages = (array)$request->json('pages');
      $entry = Entry::where('id', $id)->first();
      $element = json_decode($entry->element, true);

      $count=1;
      $newPages = array();
      foreach($pages as $page) {
        $page['page_count'] = $count;
        $newPages[]=$page;
        $count++;
      }

      $element['pages'] = $newPages;
      Entry::whereId($id)->update(['element'=>json_encode($element)]);

      return $this->prepareResult(true, $pages, $error, "Pages order updated successfully");
    }

    public function letterTranscribe(Request $request, $id)
    {
        $msg = "Entry found";
        $status = true;

        // load entry
        $entry = Entry::where([
            ['id', '=', $id],
            ['transcription_status','>',-1]
          ])->first();
        if ($entry != null) {

          // associate transcription with user
          Auth::user()->transcriptions()->sync([$id],false);

          $coll = json_decode($entry->element, true);
          // handle entry lock
          if (!$entry->handleEntryLock()) {
            $status = false;
            return $this->prepareResult($status, $coll, [], "This entry is in use by another user!", "Request complete");
          }
        }
        else {
          $coll = "";
          $msg = "Entry not found";
          $status = false;
          return $this->prepareResult($status, $coll, [], $msg, "Request failed");
        };

        return $this->prepareResult($status, $coll, [], $msg, "Request complete");

    }


    public function updateTranscriptionPage(Request $request, $id){
      $error = "";
      $entry = Entry::where('id', $id)->first();
      // handle entry lock
      if (!$entry->handleEntryLock()) {
        $status = false;
        return $this->prepareResult($status, [], [], "This entry is in use by another user!", "Request complete");
      }
      if ($entry->transcription_status!==0) {
        $error = "This item cannot be transcribed";
        return $this->prepareResult(true, $id, $error, "Page transcription error");
      }
      $archive_filename = $request->archive_filename;
      $transcription = $request->transcription;
      $element = json_decode($entry->element, true);
      $pages = $element['pages'];
      $newPages = array();
      foreach($pages as $page) {
        if ($page['archive_filename']===$archive_filename) {
          $page['transcription'] = addslashes($transcription);
        }
        $newPages[]=$page;
      }
      $element['pages']=$newPages;
      Entry::whereId($id)->update(['element'=>json_encode($element)]);
      return $this->prepareResult(true, $newPages, $error, "Page transcription updated successfully");
    }

    public function deleteLetterPage(Request $request) {
      $error = array();
      //print_r($request->pages);
      $id = intval($request->json('id'));
      $archive_filename = $request->json('archive_filename');
      $entry = Entry::where('id', $id)->first();
      $element = json_decode($entry->element, true);
      $pages = $element['pages'];
      $newPages = array();
      $i=1;
      foreach($pages as $page) {
        if ($page['archive_filename']!==$archive_filename) {
          $page['page_count']=$i;
          $newPages[]=$page;
          $i++;
        }
      }
      // delete file from storage
      Storage::disk('fullsize')->delete($archive_filename);
      Storage::disk('thumbnails')->delete($archive_filename);
      $element['pages'] = $newPages;
      Entry::whereId($id)->update(['element'=>json_encode($element)]);
      Uploadedfile::where("filename",$archive_filename)->delete();

      return $this->prepareResult(true, $newPages, $error, "Page deleted successfully");
    }

    public function deleteLetter(Request $request) {
      $error = array();
      //print_r($request->pages);
      $id = intval($request->json('id'));
      $entry = Entry::where('id', $id)->first();
      $response = $entry->deleteEntry($entry, Auth::user()->id);

      return $this->prepareResult(true, $response, $error, "Letter deleted successfully");
    }

    public function removeTranscriptionAssociation(Request $request) {
      $id = $request->input('id');
      if ($id>0) {
        Auth::user()->transcriptions()->detach($id);
      }
      return $this->prepareResult(true, [], [], "User transcriptions removed succesfully");
    }


    public function fullsearch(Request $request, $sentence)
    {

        $sanitize_sentence = filter_var($sentence, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

        $match_sql = "match(`pages`.`title`,`pages`.`description`,`pages`.`text_body`) against ('$sanitize_sentence' in boolean mode)";

        $paginate = 10;
        if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
            $paginate = $request->input('paginate');
        }

        $pages = Pages::select('entry_id', 'title', 'description', DB::raw(($match_sql) . "as score"), 'page_number')
          ->join('entry', 'entry.id','=','pages.entry_id')
          ->with('entry')
          ->where([
            ['entry.current_version','=',1],
            ['entry.status','=',1],
            ['entry.transcription_status','=',2],
            ['pages.transcription_status','=',2]
            ])
          ->whereRaw($match_sql)
          ->orderBy('score', 'desc')
          ->paginate($paginate);
        return $this->prepareResult(true, $pages, $sanitize_sentence, "Results created");

    }

    public function search(Request $request, $sentence) {
        $sort_col = "element->title";
        $sort_dir = "desc";
        $status = 1;
        $transcription_status = 2;
        $paginate = 10;
        $page = 1;
        if ($request->input('status') !== null && $request->input('status') !== "") {
            $status = $request->input('status');
        }
        if ($request->input('transcription_status') !== null && $request->input('transcription_status') !== "") {
            $transcription_status = $request->input('transcription_status');
        }
        if ($request->input('sort_col') !== null && $request->input('sort_col') !== "") {
            $sort_col = $request->input('sort_col');
        }
        if ($request->input('sort_dir') !== null && $request->input('sort_dir') !== "") {
            $sort_dir = $request->input('sort_dir');
        }
        if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
            $paginate = $request->input('paginate');
        }
        if ($request->input('page') !== null && $request->input('page') !== "") {
            $page = $request->input('page');
        }

        $sanitize_sentence = filter_var(strtolower($sentence), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

        $where_q = [
          ['status','=',1],
          ['current_version','=',1],
          ['transcription_status','=',2],
        ];

        $match_sql = " (LOWER(`element`->>'$.title') like '%".$sanitize_sentence."%' or LOWER(`element`->>'$.description') like '%".$sanitize_sentence."%' or LOWER(`fulltext`) like '%".$sanitize_sentence."%' )";
        $entries = Entry::select('entry.*')
          ->where($where_q)
          ->whereRaw(DB::Raw($match_sql))
          ->groupBy('id')
          ->orderBy($sort_col, $sort_dir)
          ->paginate($paginate);

        return $this->prepareResult(true, $entries, [], "Results created");

    }

    public function viewtopics(Request $request, $expr = "")
    {
      if(empty($expr)) {
          $status = null;
          $transcription_status = null;
          if ($request->input('status')!=="") {
            $status = $request->input('status');
          }
          if ($request->input('transcription_status')!=="") {
            $transcription_status = $request->input('transcription_status');
          }
          $where_q = [
            ['entry.current_version','=',1]
          ];
          if ($status!==null) {
            $where_q[] = ['entry.status','=', $status];
          }
          if ($transcription_status!==null) {
            $where_q[] = ['entry.transcription_status','=', $transcription_status];
          }
          $results = array();
          $topic_ids = array();
          $topic_ids_results = EntryTopic::select('entry_topic.topic_id')
            ->join('entry', 'entry.id','=','entry_topic.entry_id')
            ->where($where_q)
            ->get();
          foreach($topic_ids_results as $topic_ids_result) {
            $topic_ids[]=$topic_ids_result['topic_id'];
          }
          $rootTopics = Topic::select('id', 'name', 'count')
            ->whereIn('id', $topic_ids)
            ->where([
            ['parent_id', '=', '0'],
            ['count', '>', '0'],
            ])
            ->orderBy('name', 'asc')
            ->get();

          foreach ($rootTopics as $rootTopic) {
            $rootTopic['children'] = Topic::getTopicsChildren($rootTopic['id']);
            $results[]=$rootTopic;
          }

      } else {
          $topic = Topic::select('id')->where('name','=',$expr)->firstOrFail();
          $results = EntryTopic::select('entry_id')->where('topic_id','=',$topic->id)->get();

      }

      return  $this->prepareResult(true,$results, [],"Results created");
    }

    public function viewtopicsbyid(Request $request, $ids)
    {
      $ids_arr = explode(",", $ids);
      $entry_ids = EntryTopic::select('entry_id')->whereIn('topic_id',$ids_arr)->get();

      if (count($entry_ids)>0) {
          $sort = "asc";
          $paginate = 10;
          if ($request->input('sort') !== "") {
              $sort = $request->input('sort');
          }
          if ($request->input('paginate') !== "") {
              $paginate = $request->input('paginate');
          }
          $coll = Entry::whereIn('id',$entry_ids)->where('current_version', 1)->orderBy('created_at', $sort)->paginate($paginate);
      } else {
          $coll = "empty bottle";
      };

      return $this->prepareResult(true, $coll, [], "All user entries");
    }


    /**
     * Get a validator for an incoming ApiIngestion request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  $type
     * @return \Illuminate\Contracts\Validation\Validator
     */


    public function sources(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }

      $sources = Entry::select(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) as source, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->where($where_q)
      ->groupBy(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->orderBy(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->get();

      return $this->prepareResult(true, $sources, [], "All user entries");
    }

    public function authors(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $creators = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator')) as creator, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->get();

      return $this->prepareResult(true, $creators, [], "All user entries");
    }

    public function recipients(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $recipients = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient')) as recipient, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->get();

      return $this->prepareResult(true, $recipients, [], "All user entries");
    }

    public function people(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $creators = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator')) as person"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->get();

      $recipients = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient')) as person"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->get();

      $people = [];
      foreach ($creators as $creator) {
        $people[] = $creator['person'];
      }
      foreach($recipients as $recipient) {
        $new_person = $recipient['person'];
        if (!in_array($new_person, $people)) {
          $people[] = $new_person;
        }
      }
      sort($people);

      return $this->prepareResult(true, $people, [], "All user entries");
    }

    public function genders(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender')) as gender, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->get();

      return $this->prepareResult(true, $genders, [], "All user entries");
    }

    public function languages(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language')) as language, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->get();

      return $this->prepareResult(true, $genders, [], "All user entries");
    }

    public function date_created(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created')) as date_created, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))"))
      ->get();

      return $this->prepareResult(true, $genders, [], "All user entries");
    }

    public function indexfiltered(Request $request)
    {
      $sort = "asc";
      $paginate = 10;
      $status = null;
      $transcription_status = null;
      $keywords_ids = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $date_start = null;
      $date_end = null;

      if ($request->input('sort')!=="") {
        $sort = $request->input('sort');
      }
      if ($request->input('paginate')!=="") {
        $paginate = $request->input('paginate');
      }
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      if ($request->input('keywords')) {
        $keywords_ids = $request->input('keywords');
      }
      if ($request->input('sources')) {
        $sources = $request->input('sources');
      }
      if ($request->input('authors')) {
        $authors = $request->input('authors');
      }
      if ($request->input('genders')) {
        $genders = $request->input('genders');
      }
      if ($request->input('languages')) {
        $languages = $request->input('languages');
      }
      if ($request->input('date_start')) {
        $date_start = $request->input('date_start');
      }
      if ($request->input('date_end')) {
        $date_end = $request->input('date_end');
      }

      if ($status===null || $transcription_status===null) {
        return $this->prepareResult(true, [], [], "Please set the status and transcription status.");
      }

      $status = 1;
      $transcription_status = 2;

      $entry_ids = array();
      // keywords
      if (count($keywords_ids)>0) {
        $keywords_ids_num = count($keywords_ids);
        $keywords_entry_ids = EntryTopic::select('entry_id', DB::raw('COUNT(entry_id) as c'))
        ->whereIn('topic_id',$keywords_ids)
        ->groupBy('entry_id')
        ->havingRaw('c='.$keywords_ids_num)
        ->get();
        $keywords_entry_ids = $keywords_entry_ids->toArray();
        $new_keywords_entry_ids = array();
        foreach($keywords_entry_ids as $keywords_entry_id) {
          $new_keywords_entry_ids[]=$keywords_entry_id['entry_id'];
        }
        $entry_ids[] = $new_keywords_entry_ids;
      }
      // sources
      if (count($sources)>0) {
        $new_sources = $this->inputArraytoString($sources);
        $sources_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) IN (".$new_sources.")"))
          ->get();
        $sources_entry_ids = $sources_ids->toArray();
        $sources_entry_ids = $this->returnIdsArray($sources_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$sources_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $sources_entry_ids;
        }
      }
      // authors
      if (count($authors)>0) {
        $new_authors = $this->inputArraytoString($authors);
        $authors_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))) IN (".$new_authors.")"))
          ->get();
        $authors_entry_ids = $authors_ids->toArray();
        $authors_entry_ids = $this->returnIdsArray($authors_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$authors_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $authors_entry_ids;
        }
      }
      // genders
      if (count($genders)>0) {
        $new_genders = $this->inputArraytoString($genders);
        $genders_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))) IN (".$new_genders.")"))
          ->get();
        $genders_entry_ids = $genders_ids->toArray();
        $genders_entry_ids = $this->returnIdsArray($genders_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$genders_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $genders_entry_ids;
        }
      }
      // languages
      if (count($languages)>0) {
        $new_languages = $this->inputArraytoString($languages);
        $languages_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))) IN (".$new_languages.")"))
          ->get();
        $languages_entry_ids = $languages_ids->toArray();
        $languages_entry_ids = $this->returnIdsArray($languages_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$languages_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $languages_entry_ids;
        }
      }
      // date_created
      if ($date_start!==null || $date_end!==null) {
        if ($date_start!==null && $date_end===null) {
          $date_end = $date_start;
        }
        $date_start = date($date_start);
        $date_end = date($date_end);
        $date_created_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereBetween(DB::raw("CAST(TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))) AS DATE)"), [$date_start, $date_end])
          ->get();
        if(count($date_created_ids)>0) {
          $date_created_entry_ids = $date_created_ids->toArray();
          $date_created_entry_ids = $this->returnIdsArray($date_created_entry_ids);
          if (!empty($entry_ids[0])) {
            $new_ids = array();
            foreach($entry_ids[0] as $entry_id) {
              if (in_array($entry_id,$date_created_entry_ids)) {
                $new_ids[]=$entry_id;
              }
            }
            $entry_ids[0] = $new_ids;
          }
          else {
            $entry_ids[] = $date_created_entry_ids;
          }
        }
        else $entry_ids[] = [];

      }

      if (count($entry_ids)>0) {
          $coll = Entry::whereIn('id',$entry_ids[0])
            ->where([
              ['current_version','=', 1],
              ['status','=', $status],
              ['transcription_status','=', $transcription_status],
              ])
            ->orderBy('created_at', $sort)
            ->paginate($paginate);
      } else {
        if (Entry::first() != null) {
          $coll = Entry::where([
            ['current_version','=', 1],
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
            ->orderBy('created_at', $sort)
            ->paginate($paginate);
        } else {
            $coll = "empty bottle";
        };
      };

      return $this->prepareResult(true, $coll, [], "All user entries");
    }


    public function transcriptionsDeskfiltered(Request $request)
    {
      $sort = "asc";
      $page = 1;
      $paginate = 10;
      $status = null;
      $transcription_status = null;
      $keywords_ids = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $date_start = null;
      $date_end = null;

      if ($request->input('sort')!=="") {
        $sort = $request->input('sort');
      }
      if ($request->input('page')!=="") {
        $page = $request->input('page');
      }
      if ($request->input('paginate')!=="") {
        $paginate = $request->input('paginate');
      }
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      if ($request->input('keywords')) {
        $keywords_ids = $request->input('keywords');
      }
      if ($request->input('sources')) {
        $sources = $request->input('sources');
      }
      if ($request->input('authors')) {
        $authors = $request->input('authors');
      }
      if ($request->input('genders')) {
        $genders = $request->input('genders');
      }
      if ($request->input('languages')) {
        $languages = $request->input('languages');
      }
      if ($request->input('date_start')) {
        $date_start = $request->input('date_start');
      }
      if ($request->input('date_end')) {
        $date_end = $request->input('date_end');
      }

      if ($status===null || $transcription_status===null) {
        return $this->prepareResult(true, [], [], "Please set the status and transcription status.");
      }
      $status = 0;

      $entry_ids = array();
      // keywords
      if (count($keywords_ids)>0) {
        $keywords_ids_num = count($keywords_ids);
        $keywords_entry_ids = EntryTopic::select('entry_id', DB::raw('COUNT(entry_id) as c'))
        ->whereIn('topic_id',$keywords_ids)
        ->groupBy('entry_id')
        ->havingRaw('c='.$keywords_ids_num)
        ->get();
        $keywords_entry_ids = $keywords_entry_ids->toArray();
        $new_keywords_entry_ids = array();
        foreach($keywords_entry_ids as $keywords_entry_id) {
          $new_keywords_entry_ids[]=$keywords_entry_id['entry_id'];
        }
        $entry_ids[] = $new_keywords_entry_ids;
      }
      // sources
      if (count($sources)>0) {
        $new_sources = $this->inputArraytoString($sources);
        $sources_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','>', -1],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) IN (".$new_sources.")"))
          ->get();
        $sources_entry_ids = $sources_ids->toArray();
        $sources_entry_ids = $this->returnIdsArray($sources_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$sources_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $sources_entry_ids;
        }
      }
      // authors
      if (count($authors)>0) {
        $new_authors = $this->inputArraytoString($authors);
        $authors_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','>', -1],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))) IN (".$new_authors.")"))
          ->get();
        $authors_entry_ids = $authors_ids->toArray();
        $authors_entry_ids = $this->returnIdsArray($authors_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$authors_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $authors_entry_ids;
        }
      }
      // genders
      if (count($genders)>0) {
        $new_genders = $this->inputArraytoString($genders);
        $genders_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','>', -1],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))) IN (".$new_genders.")"))
          ->get();
        $genders_entry_ids = $genders_ids->toArray();
        $genders_entry_ids = $this->returnIdsArray($genders_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$genders_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $genders_entry_ids;
        }
      }
      // languages
      if (count($languages)>0) {
        $new_languages = $this->inputArraytoString($languages);
        $languages_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','>', -1],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))) IN (".$new_languages.")"))
          ->get();
        $languages_entry_ids = $languages_ids->toArray();
        $languages_entry_ids = $this->returnIdsArray($languages_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$languages_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $languages_entry_ids;
        }
      }
      // date_created
      if ($date_start!==null || $date_end!==null) {
        if ($date_start!==null && $date_end===null) {
          $date_end = $date_start;
        }
        $date_start = date($date_start);
        $date_end = date($date_end);
        $date_created_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','>', -1],
            ])
          ->whereBetween(DB::raw("CAST(TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))) AS DATE)"), [$date_start, $date_end])
          ->get();
        if(count($date_created_ids)>0) {
          $date_created_entry_ids = $date_created_ids->toArray();
          $date_created_entry_ids = $this->returnIdsArray($date_created_entry_ids);
          if (!empty($entry_ids[0])) {
            $new_ids = array();
            foreach($entry_ids[0] as $entry_id) {
              if (in_array($entry_id,$date_created_entry_ids)) {
                $new_ids[]=$entry_id;
              }
            }
            $entry_ids[0] = $new_ids;
          }
          else {
            $entry_ids[] = $date_created_entry_ids;
          }
        }
        else $entry_ids[] = [];

      }

      if (count($entry_ids)>0) {
          $items = Entry::whereIn('id',$entry_ids[0])
            ->where([
              ['status','=', $status],
              ['transcription_status','>', -1],
              ])
            ->orderBy('completed',$sort)
            ->paginate($paginate);



      } else {
        if (Entry::first() != null) {
          $items = Entry::where([
            ['status','=', $status],
            ['transcription_status','>', -1],
            ])
          ->orderBy('completed',$sort)
          ->paginate($paginate);

        } else {
            $coll = "empty bottle";
        };
      };

      return $this->prepareResult(true, $items, [], "All user entries");
    }

    public function indexfilteredFilters(Request $request)
    {
      $sort = "asc";
      $status = null;
      $transcription_status = null;
      $keywords_ids = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $date_start = null;
      $date_end = null;

      if ($request->input('sort')!=="") {
        $sort = $request->input('sort');
      }
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      if ($request->input('keywords')) {
        $keywords_ids = $request->input('keywords');
      }
      if ($request->input('sources')) {
        $sources = $request->input('sources');
      }
      if ($request->input('authors')) {
        $authors = $request->input('authors');
      }
      if ($request->input('genders')) {
        $genders = $request->input('genders');
      }
      if ($request->input('languages')) {
        $languages = $request->input('languages');
      }
      if ($request->input('date_start')) {
        $date_start = $request->input('date_start');
      }
      if ($request->input('date_end')) {
        $date_end = $request->input('date_end');
      }

      if ($status===null || $transcription_status===null) {
        return $this->prepareResult(true, [], [], "Please set the status and transcription status.");
      }

      $entry_ids = array();
      // keywords
      if (count($keywords_ids)>0) {
        $keywords_ids_num = count($keywords_ids);
        $keywords_entry_ids = EntryTopic::select('entry_id', DB::raw('COUNT(entry_id) as c'))
        ->whereIn('topic_id',$keywords_ids)
        ->groupBy('entry_id')
        ->havingRaw('c='.$keywords_ids_num)
        ->get();
        $keywords_entry_ids = $keywords_entry_ids->toArray();
        $new_keywords_entry_ids = array();
        foreach($keywords_entry_ids as $keywords_entry_id) {
          $new_keywords_entry_ids[]=$keywords_entry_id['entry_id'];
        }
        $entry_ids[] = $new_keywords_entry_ids;
      }

      /// sources
      if (count($sources)>0) {
        $new_sources = $this->inputArraytoString($sources);
        $sources_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) IN (".$new_sources.")"))
          ->get();
        $sources_entry_ids = $sources_ids->toArray();
        $sources_entry_ids = $this->returnIdsArray($sources_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$sources_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $sources_entry_ids;
        }
      }

      // authors
      if (count($authors)>0) {
        $new_authors = $this->inputArraytoString($authors);
        $authors_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))) IN (".$new_authors.")"))
          ->get();
        $authors_entry_ids = $authors_ids->toArray();
        $authors_entry_ids = $this->returnIdsArray($authors_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$authors_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $authors_entry_ids;
        }
      }

      // genders
      if (count($genders)>0) {
        $new_genders = $this->inputArraytoString($genders);
        $genders_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))) IN (".$new_genders.")"))
          ->get();
        $genders_entry_ids = $genders_ids->toArray();
        $genders_entry_ids = $this->returnIdsArray($genders_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$genders_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $genders_entry_ids;
        }
      }

      // languages
      if (count($languages)>0) {
        $new_languages = $this->inputArraytoString($languages);
        $languages_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))) IN (".$new_languages.")"))
          ->get();
        $languages_entry_ids = $languages_ids->toArray();
        $languages_entry_ids = $this->returnIdsArray($languages_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$languages_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $languages_entry_ids;
        }
      }

      // date_created
      if ($date_start!==null || $date_end!==null) {
        if ($date_start!==null && $date_end===null) {
          $date_end = $date_start;
        }
        $date_start = date($date_start);
        $date_end = date($date_end);
        $date_created_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ['current_version','=',1]
            ])
          ->whereBetween(DB::raw("CAST(TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))) AS DATE)"), [$date_start, $date_end])
          ->get();
        if(count($date_created_ids)>0) {
          $date_created_entry_ids = $date_created_ids->toArray();
          $date_created_entry_ids = $this->returnIdsArray($date_created_entry_ids);
          if (!empty($entry_ids[0])) {
            $new_ids = array();
            foreach($entry_ids[0] as $entry_id) {
              if (in_array($entry_id,$date_created_entry_ids)) {
                $new_ids[]=$entry_id;
              }
            }
            $entry_ids[0] = $new_ids;
          }
          else {
            $entry_ids[] = $date_created_entry_ids;
          }
        }
        else $entry_ids[] = [];
      }

      if (count($entry_ids)>0) {
          $coll = Entry::whereIn('id',$entry_ids[0])
            ->where([
              ['current_version','=', 1],
              ['status','=', $status],
              ['transcription_status','=', $transcription_status],
              ])
            ->orderBy('created_at', $sort)
            ->get();
      } else {
        if (Entry::first() != null) {
          $coll = Entry::where([
            ['current_version','=', 1],
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->orderBy('created_at', $sort)
          ->get();
        } else {
            $coll = "empty bottle";
        };
      };

      //dd($entry_ids);
      $keywords = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $dates_sent = array();

      for ($i=0;$i<count($coll); $i++) {
        $coll_decode = json_decode($coll[$i]["element"], true);
        // keywords
        if (isset($coll_decode["topics"])) {
          $coll_keywords = $coll_decode["topics"];
          for ($k=0;$k<count($coll_keywords);$k++) {
            $coll_keyword = $coll_keywords[$k]['topic_name'];
            $keywords[]=$coll_keyword;
          }
        }

        //sources
        if (isset($coll_decode["source"])) {
          $coll_source = $coll_decode["source"];
          $sources[]=$coll_source;
        }
        //authors
        if (isset($coll_decode["creator"])) {
          $coll_author = $coll_decode["creator"];
          $authors[]=$coll_author;
        }

        //gender
        if (isset($coll_decode["creator_gender"])) {
          $coll_gender = $coll_decode["creator_gender"];
          $genders[]=$coll_gender;
        }
        //language
        if (isset($coll_decode["language"])) {
          $coll_language = $coll_decode["language"];
          $languages[]=$coll_language;
        }
        //dates_sent
        if (isset($coll_decode["date_created"])) {
          $coll_date_sent = $coll_decode["date_created"];
          $dates_sent[]=$coll_date_sent;
        }
      }
      //dd($keywords);
      $data = array(
        "keywords"=> array_count_values($keywords),
        "sources"=> array_count_values($sources),
        "authors"=> array_count_values($authors),
        "genders"=> array_count_values($genders),
        "languages"=> array_count_values($languages),
        "dates_sent"=> array_count_values($dates_sent),
      );

      return $this->prepareResult(true, $data, [], "All user entries");
    }

    public function rights(Request $request) {
      $rights = Rights::where("status",1)->get();
      return $this->prepareResult(true, $rights, [], "All available rights");
    }
}
