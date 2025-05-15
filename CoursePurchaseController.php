<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Courses;
use Illuminate\Http\Request;

class CoursePurchaseController extends Controller
{
    // Buy a course
    public function buyCourse(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = User::find($request->user_id);
        $courseId = $request->course_id;

        // Get the course record
        $course = Courses::find($courseId);

        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        // Check if course already purchased
        if (!$user->courses()->where('course_id', $courseId)->exists()) {
            // Save to pivot with course name
            $user->courses()->attach($courseId, [
                'name' => $course->course, // Replace with $course->name if needed
            ]);

            return response()->json(['message' => 'Course bought successfully']);
        }

        return response()->json(['message' => 'Course already bought'], 409);
    }

    // Get all purchased courses for a user
    public function getPurchasedCourses($userId)
    {
        $user = User::with('courses:id,course')->find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $courses = $user->courses()->select('courses.id', 'courses.course')->get();

        return response()->json([
            'user_id' => $user->id,
            'purchased_courses' => $courses->map(function ($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->course,
                ];
            }),
        ]);
    }
}
