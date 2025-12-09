<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class CmsController extends Controller {
  public function index() {
    $dashboard_welcome = DB::selectOne("SELECT content FROM cms_blocks WHERE slug = 'dashboard_welcome' AND is_active = TRUE")?->content;
    $dashboard_unsafe = DB::selectOne("SELECT content FROM cms_blocks WHERE slug = 'dashboard_unsafe' AND is_active = TRUE")?->content;
    $dashboard_not_found = DB::selectOne("SELECT content FROM cms_blocks WHERE slug = 'dashboard_not_found' AND is_active = TRUE")?->content;
    return view('cms', compact('dashboard_welcome', 'dashboard_unsafe', 'dashboard_not_found'));
  }

  public function page(string $slug) {
    $row = DB::selectOne("SELECT title, content FROM cms_blocks WHERE slug = ? AND is_active = TRUE", [$slug]);
    if (!$row) abort(404);
    return response()->view('cms.page', ['title' => $row->title, 'html' => $row->content]);
  }
}
