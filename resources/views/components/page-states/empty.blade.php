@props([
	'title' => '',
	'desc' => '',
])

<div class="bg-bg-pr rounded-2xl px-6 py-40 text-center shadow-2xl shadow-fill-qt">
	<h4 class="text-par-m font-medium text-lab-pr2">
		{{ $title }}
	</h4>
	<p class="text-par-s text-lab-sc">
		{{ $desc }}
	</p>
</div>