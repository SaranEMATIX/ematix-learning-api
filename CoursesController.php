<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Courses;

class CoursesController extends Controller
{
    protected $courses;
    public function __construct()
    {
        $this->courses = new Courses();
    }
    public function index()
    {
        return $this->courses->all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return $this->courses->create($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $student = $this->courses->find($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $course = $this->courses->find($id);

        if (!$course) {
            return response()->json([
                'message' => 'Course not found.'
            ], 404);
        }

        $course->update($request->all());

        return response()->json([
            'message' => 'Course updated successfully.',
            'data' => $course
        ], 200);
    }




    public function destroy(string $id)
    {
        $courses = $this->courses->find($id);
        return $courses->delete();
    }

    public function dashboard()
    {
        $courses = $this->courses->all();
        return view('dashboard', compact('courses'));
    }

    // Show Create Course Form
    public function create()
    {
        return view('courses.create');
    }

    // Store New Course
    public function dbstore(Request $request)
    {
        $validated = $request->validate([
            'course' => 'required|string|max:255',
            'rate' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'purchase' => 'nullable|numeric',
            // 'user_id' => 'required|integer',
        ]);

        $this->courses->create($validated);

        return redirect()->route('dashboard')->with('success', 'Course created successfully!');
    }

    // Show Edit Course Form
    public function edit($id)
    {
        $course = $this->courses->find($id);
        if (!$course) {
            return redirect()->route('dashboard')->with('error', 'Course not found');
        }
        return view('courses.edit', compact('course'));
    }

    // Update Course
    public function dbupdate(Request $request, $id)
    {
        $course = $this->courses->find($id);
        if (!$course) {
            return redirect()->route('dashboard')->with('error', 'Course not found');
        }

        $validated = $request->validate([
            'course' => 'required|string|max:255',
            'rate' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'purchase' => 'nullable|numeric',
            // 'user_id' => 'required|integer',
        ]);

        $course->update($validated);

        return redirect()->route('dashboard')->with('success', 'Course updated successfully!');
    }

    // Delete a Course
    public function dbdestroy($id)
    {
        $course = $this->courses->find($id);
        if ($course) {
            $course->delete();
        }

        return redirect()->route('dashboard')->with('success', 'Course deleted successfully!');
    }
}
