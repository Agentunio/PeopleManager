@include('errors.guest', [
    'status' => 410,
    'title' => 'Link aktywacyjny wygasł',
    'description' => 'Ze względów bezpieczeństwa link aktywacyjny nie jest już ważny. Poproś administratora o wysłanie nowego.',
])
