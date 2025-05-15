<?php
namespace App\Http\Controllers;
use App\Models\Favorite;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\ModuleUserStatus;
use App\Models\MyCourse1;
use App\Models\User;

class CategoryController extends Controller
{

    public function getCategoryList()
    {
        try {

            $categories = Category::all();

            return response()->json([
                'message' => 'Categories fetched successfully',
                'data' => $categories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // ✅ Create Category
    public function storeCategory(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|unique:categories,name',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('categories', 'public');
            }


            $category = Category::create([
                'name' => $request->name,
                'image' => $imagePath
            ]);

            return response()->json([
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // ✅ Read All Categories
    public function index()
    {
        $categories = Category::with('subcategories')->get();
        return response()->json(['data' => $categories], 200);
    }

    // ✅ Show Single Category
    public function showCategory($id)
    {
        $category = Category::with('subcategories')->find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        return response()->json(['data' => $category], 200);
    }

    // ✅ Update Category
    public function updateCategory(Request $request, $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $request->validate([
            'name' => 'required|unique:categories,name,' . $id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
            $category->image = $imagePath;
        }

        $category->name = $request->name;
        $category->save();

        return response()->json(['message' => 'Category updated', 'data' => $category], 200);
    }


    // ✅ Delete Category
    public function deleteCategory($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $category->delete();
        return response()->json(['message' => 'Category deleted'], 200);
    }


    // ✅ Get Subcategories of a specific Category
    public function getSubcategoriesByCategory($categoryId)
    {
        $category = Category::with('subcategories')->find($categoryId);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json([
            'category_name' => $category->name,
            'subcategories' => $category->subcategories
        ], 200);
    }




    // ✅ Read All Subcategories
    public function getAllSubcategories()
    {
        $subcategories = Subcategory::with('category')->get();
        return response()->json(['data' => $subcategories], 200);
    }


    // ✅ Create Subcategory

    public function storeSubcategory(Request $request)
    {
        try {
            // Validate the incoming request
            $request->validate([
                'name' => 'required|string',
                'category_id' => 'required|exists:categories,id',
                'rate' => 'nullable|numeric',
                'images' => 'nullable|image',
                'modules' => 'nullable|array',
                'modules.*.module_name' => 'nullable|string',
                'modules.*.video_url' => 'nullable|string',
                'modules.*.video_file' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:10000000',
                'modules.*.is_passed' => 'nullable|boolean',
                'modules.*.questions' => 'nullable|array',
                'final_module.questions' => 'nullable|array', // Validate final module questions
            ]);

            // Handle image upload if file is present
            $imagePath = null;
            if ($request->hasFile('images')) {
                $imagePath = $request->file('images')->store('subcategory', 'public');
            } elseif ($request->filled('images')) {
                // Assume it's a path from JSON
                $imagePath = $request->input('images');
            }

            // Process modules array
            $modules = [];
            if ($request->has('modules')) {
                foreach ($request->modules as $index => $module) {
                    $videoFilePath = null;

                    // Check for uploaded file
                    if ($request->hasFile("modules.$index.video_file")) {
                        $videoFilePath = $request->file("modules.$index.video_file")->store('videos', 'public');
                    } elseif (!empty($module['video_file'])) {
                        // Accept path if coming from raw JSON
                        $videoFilePath = $module['video_file'];
                    }

                    $modules[] = [
                        'module_id' => 'module_' . uniqid(),
                        'module_name' => $module['module_name'] ?? null,
                        'video_url' => $module['video_url'] ?? null,
                        'video_file' => $videoFilePath,
                        'is_passed' => $module['is_passed'] ?? false,
                        'questions' => $module['questions'] ?? [],
                    ];
                }
            }

            // Handle the final_module (if provided)
            $finalModule = null;
            if ($request->has('final_module')) {
                $finalModule = [
                    'module_id' => 'final_module_' . uniqid(),
                    'questions' => $request->input('final_module.questions', []), // Default to an empty array if no questions
                ];
            }

            // Create the subcategory
            $subcategory = Subcategory::create([
                'name' => $request->name,
                'category_id' => $request->category_id,
                'rate' => $request->rate,
                'images' => $imagePath,
                'modules' => $modules,
                'final_module' => $finalModule, // Add final module to the subcategory
            ]);

            return response()->json([
                'message' => 'Subcategory created successfully',
                'data' => [
                    'subcategory' => $subcategory,
                    'modules' => $subcategory->modules,
                    'final_module' => $subcategory->final_module, // Return the final module
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create subcategory',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // public function storeSubcategory(Request $request)
    // {
    //     try {
    //         // Validate request
    //         $request->validate([
    //             'name' => 'required',
    //             'category_id' => 'required|exists:categories,id',
    //             'rate' => 'nullable|numeric',
    //             'images' => 'nullable|file|image',
    //             'modules' => 'nullable|array',
    //             'modules.*.module_name' => 'nullable|string',
    //             'modules.*.video_url' => 'nullable|string',
    //             'modules.*.video_file' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:10000000',
    //             // 'modules.*is_passed'=>"nullable|boolean",
    //             'modules.*.is_passed' => 'nullable|boolean',

    //         ]);

    //         // Upload image if exists
    //         $imagePath = null;
    //         if ($request->hasFile('images')) {
    //             $imagePath = $request->file('images')->store('subcategory', 'public');
    //         }

    //         // Handle modules array
    //         $modules = [];
    //         if ($request->has('modules')) {
    //             foreach ($request->modules as $index => $module) {
    //                 $videoFilePath = null;

    //                 // Upload video if exists
    //                 if ($request->hasFile("modules.$index.video_file")) {
    //                     $videoFilePath = $request->file("modules.$index.video_file")->store('videos', 'public');
    //                 }

    //                 // Add module ID using the index or another method like a unique slug
    //                 $modules[] = [
    //                     'module_id' => 'module _' . uniqid(),
    //                     'module_name' => $module['module_name'] ?? null,

    //                     'is_passed' => $module['is_passed'] ?? false,

    //                     'video_url' => $module['video_url'] ?? null,
    //                     'video_file' => $videoFilePath,
    //                     'questions' => $module['questions'] ?? [],
    //                 ];
    //             }
    //         }

    //         // Create the subcategory with the modules
    //         $subcategory = Subcategory::create([
    //             'name' => $request->name,
    //             'category_id' => $request->category_id,
    //             'rate' => $request->rate,
    //             'images' => $imagePath,
    //             'modules' => $modules,
    //         ]);

    //         // Debugging step (optional)
    //         // dd($subcategory); // Uncomment to inspect data

    //         return response()->json([
    //             'message' => 'Subcategory created successfully',
    //             'data' => [
    //                 'subcategory' => $subcategory,
    //                 'modules' => $subcategory->modules,  // Ensure modules are returned
    //             ]
    //         ], 201);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Failed to create subcategory',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }





    // ✅ Show Single Subcategory
    public function showSubcategory($id)
    {
        $subcategory = Subcategory::with('category')->find($id);
        if (!$subcategory) {
            return response()->json(['message' => 'Subcategory not found'], 404);
        }
        return response()->json(['data' => $subcategory], 200);
    }



    public function updateSubcategory(Request $request, $id)
    {
        $subcategory = Subcategory::find($id);

        if (!$subcategory) {
            return response()->json(['message' => 'Subcategory not found'], 404);
        }

        // ✅ Validate input
        $request->validate([
            'name' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'rate' => 'nullable|numeric',
            'images' => 'nullable|file|image|max:2048',
            'modules' => 'nullable|array',
            'modules.*.module_id' => 'nullable|string',
            'modules.*.module_name' => 'nullable|string',
            'modules.*.video_url' => 'nullable|string',
            'modules.*.video_file' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:10000000',
            'modules.*.is_passed' => 'nullable|boolean',
            'modules.*.questions' => 'nullable|array',

            // ✅ Final Module validation
            'final_module' => 'nullable|array',
            'final_module.questions' => 'nullable|array',
            'final_module.questions.*.question_text' => 'required|string',
            'final_module.questions.*.option_1' => 'required|string',
            'final_module.questions.*.option_2' => 'required|string',
            'final_module.questions.*.option_3' => 'required|string',
            'final_module.questions.*.option_4' => 'required|string',
            'final_module.questions.*.correct_option' => 'required|string',
        ]);

        // ✅ Handle image upload
        $imagePath = $subcategory->images;
        if ($request->hasFile('images')) {
            $imagePath = $request->file('images')->store('subcategory', 'public');
        }

        // ✅ Handle modules
        $modules = [];
        if ($request->has('modules')) {
            foreach ($request->modules as $index => $module) {
                // Handle video file upload
                $videoFilePath = null;
                if ($request->hasFile("modules.$index.video_file")) {
                    $videoFilePath = $request->file("modules.$index.video_file")->store('videos', 'public');
                } elseif (isset($subcategory->modules[$index]['video_file'])) {
                    $videoFilePath = $subcategory->modules[$index]['video_file'];
                }

                $modules[] = [
                    'module_id' => $module['module_id'] ?? ($subcategory->modules[$index]['module_id'] ?? (string) Str::uuid()),
                    'module_name' => $module['module_name'] ?? null,
                    'video_url' => $module['video_url'] ?? null,
                    'video_file' => $videoFilePath,
                    'is_passed' => $module['is_passed'] ?? false,
                    'questions' => $module['questions'] ?? [],
                ];
            }
        }

        // ✅ Update the subcategory
        $subcategory->update([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'rate' => $request->rate,
            'images' => $imagePath,
            'modules' => $modules,
            'final_module' => $request->final_module, // ✅ Store final_module
        ]);

        return response()->json([
            'message' => 'Subcategory updated successfully',
            'data' => $subcategory
        ], 200);
    }

    // public function updateSubcategory(Request $request, $id)
    // {
    //     $subcategory = Subcategory::find($id);

    //     if (!$subcategory) {
    //         return response()->json(['message' => 'Subcategory not found'], 404);
    //     }

    //     // ✅ Validate input
    //     $request->validate([
    //         'name' => 'required',
    //         'category_id' => 'required|exists:categories,id',
    //         'rate' => 'nullable|numeric',
    //         'images' => 'nullable|file|image|max:2048',
    //         'modules' => 'nullable|array',
    //         'modules.*.module_id' => 'nullable|string',
    //         'modules.*.module_name' => 'nullable|string',
    //         'modules.*.video_url' => 'nullable|string',
    //         'modules.*.video_file' => 'nullable|file|mimes:mp4,mov,avi,wmv|max:10000000',
    //         'modules.*.is_passed' => 'nullable|boolean',
    //         'modules.*.questions' => 'nullable|array',
    //     ]);

    //     // ✅ Handle image
    //     $imagePath = $subcategory->images;
    //     if ($request->hasFile('images')) {
    //         $imagePath = $request->file('images')->store('subcategory', 'public');
    //     }

    //     // ✅ Handle modules
    //     $modules = [];
    //     if ($request->has('modules')) {
    //         foreach ($request->modules as $index => $module) {
    //             // Handle video file
    //             $videoFilePath = null;
    //             if ($request->hasFile("modules.$index.video_file")) {
    //                 $videoFilePath = $request->file("modules.$index.video_file")->store('videos', 'public');
    //             } elseif (isset($subcategory->modules[$index]['video_file'])) {
    //                 $videoFilePath = $subcategory->modules[$index]['video_file'];
    //             }

    //             $modules[] = [
    //                 'module_id' => $module['module_id'] ?? ($subcategory->modules[$index]['module_id'] ?? (string) Str::uuid()),
    //                 'module_name' => $module['module_name'] ?? null,
    //                 'video_url' => $module['video_url'] ?? null,
    //                 'video_file' => $videoFilePath,
    //                 'is_passed' => $module['is_passed'] ?? false,
    //                 'questions' => $module['questions'] ?? [],
    //             ];
    //         }
    //     }

    //     // ✅ Update the subcategory
    //     $subcategory->update([
    //         'name' => $request->name,
    //         'category_id' => $request->category_id,
    //         'rate' => $request->rate,
    //         'images' => $imagePath,
    //         'modules' => $modules,
    //          'final_module' => $request->final_module, // ✅ Add this line
    //     ]);

    //     return response()->json([
    //         'message' => 'Subcategory updated successfully',
    //         'data' => $subcategory
    //     ], 200);
    // }


    // ✅ Delete Subcategory
    public function deleteSubcategory($id)
    {
        $subcategory = Subcategory::find($id);
        if (!$subcategory) {
            return response()->json(['message' => 'Subcategory not found'], 404);
        }

        $subcategory->delete();
        return response()->json(['message' => 'Subcategory deleted'], 200);
    }



    public function completeModule(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'module_id' => 'required|string',
            'is_passed' => 'required|boolean',
        ]);

        try {
            $status = ModuleUserStatus::updateOrCreate(
                [
                    'user_id' => $request->user_id,
                    'module_id' => $request->module_id,
                ],
                [
                    'is_passed' => $request->is_passed,
                ]
            );

            return response()->json([
                'message' => 'Module completion recorded successfully',
                'data' => $status,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record module completion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listCompletedModules($user_id)
    {
        // Check if user exists
        if (!\App\Models\User::find($user_id)) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $completedModules = \App\Models\ModuleUserStatus::where('user_id', $user_id)->get([
            'module_id',
            'is_passed',
            'created_at',
            'updated_at'
        ]);

        return response()->json([
            'message' => 'Completed modules retrieved successfully',
            'data' => $completedModules
        ]);
    }
    public function storeMyCourse(Request $request)
    {
        $request->validate([
            'user_id'        => 'required|exists:users,id',
            'subcategory_id' => 'required|exists:subcategories,id',
        ]);

        // Check if course already exists for this user and subcategory
        $exists = MyCourse1::where('user_id', $request->user_id)
            ->where('subcategory_id', $request->subcategory_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Course already purchased by this user',
            ], 409); // 409 Conflict
        }

        $subcategory = Subcategory::with('category')->findOrFail($request->subcategory_id);

        $course = MyCourse1::create([
            'user_id'          => $request->user_id,
            'category_id'      => $subcategory->category->id,
            'category_name'    => $subcategory->category->name,
            'subcategory_id'   => $subcategory->id,
            'subcategory_name' => $subcategory->name,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Course added to user successfully',
            'data'    => [
                'user_id'           => $course->user_id,
                'category_id'       => $course->category_id,
                'category_name'     => $course->category_name,
                'subcategory_id'    => $course->subcategory_id,
                'subcategory_name'  => $course->subcategory_name,
                'rate'              => $subcategory->rate,
                'subcategory_image' => $subcategory->images,
                'modules'           => $subcategory->modules,
            ]
        ], 200);
    }



    public function getMyCourses(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    $courses = MyCourse1::where('user_id', $request->user_id)
        ->get()
        ->map(function ($course) {
            // Eager load category and get subcategory with modules + final_module
            $subcategory = Subcategory::with('category')->find($course->subcategory_id);

            return [
                'user_id'           => $course->user_id,
                'category_id'       => $course->category_id,
                'category_name'     => $course->category_name,
                'subcategory_id'    => $course->subcategory_id,
                'subcategory_name'  => $course->subcategory_name,
                'rate'              => $subcategory->rate ?? null,
                'subcategory_image' => $subcategory->images ?? null,
                'modules'           => $subcategory->modules ?? [],
                'final_module'      => $subcategory->final_module ?? null, // ✅ Added final module
            ];
        });

    return response()->json([
        'status'  => true,
        'message' => 'Courses fetched successfully',
        'data'    => $courses,
    ], 200);
}



    public function toggleFavorite(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'user_id'        => 'required|exists:users,id',
            'subcategory_id' => 'required|exists:subcategories,id',
        ]);

        // Check if course already exists for this user and subcategory
        $exists = Favorite::where('user_id', $request->user_id)
            ->where('subcategory_id', $request->subcategory_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Course already purchased by this user',
            ], 409); // 409 Conflict
        }

        $subcategory = Subcategory::with('category')->findOrFail($request->subcategory_id);

        $course = Favorite::create([
            'user_id'          => $request->user_id,
            'category_id'      => $subcategory->category->id,
            'category_name'    => $subcategory->category->name,
            'subcategory_id'   => $subcategory->id,
            'subcategory_name' => $subcategory->name,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Course added to user successfully',
            'data'    => [
                'user_id'           => $course->user_id,
                'category_id'       => $course->category_id,
                'category_name'     => $course->category_name,
                'subcategory_id'    => $course->subcategory_id,
                'subcategory_name'  => $course->subcategory_name,
                'rate'              => $subcategory->rate,
                'subcategory_image' => $subcategory->images,
                'modules'           => $subcategory->modules,
            ]
        ], 200);
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'subcategory_id' => 'required|exists:subcategories,id',
        ]);

        // Check if the favorite already exists
        $favorite = Favorite::where('user_id', $validated['user_id'])
            ->where('subcategory_id', $validated['subcategory_id'])
            ->first();

        // If the favorite doesn't exist, create it
        if (!$favorite) {
            Favorite::create($validated);
            return response()->json([
                'status' => true,
                'message' => 'Added to favorites',
                'is_favourite' => true,
            ]);
        }


        return response()->json([
            'status' => false,
            'message' => 'Already added to favorites',
            'is_favourite' => true,
        ]);
    }



    public function getFavorites(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $favorites = Favorite::with('subcategory.category')
            ->where('user_id', $request->user_id)
            ->get()
            ->map(function ($fav) {
                return [
                    'subcategory_id'   => $fav->subcategory->id,
                    'subcategory_name' => $fav->subcategory->name,
                    'category_id'      => $fav->subcategory->category->id,
                    'category_name'    => $fav->subcategory->category->name,
                    'rate'             => $fav->subcategory->rate,
                    'image'            => $fav->subcategory->images,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Favorites fetched successfully',
            'data' => $favorites,
        ]);
    }

    public function removeFavorite(Request $request)
    {
        // Validate request
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'subcategory_id' => 'required|exists:subcategories,id',
        ]);

        // Find the favorite
        $favorite = Favorite::where('user_id', $request->user_id)
            ->where('subcategory_id', $request->subcategory_id)
            ->first();

        if (!$favorite) {
            return response()->json([
                'status' => false,
                'message' => 'Favorite not found for this user.',
            ], 404);
        }

        // Delete it
        $favorite->delete();

        return response()->json([
            'status' => true,
            'message' => 'Favorite removed successfully.',
        ]);
    }
}
