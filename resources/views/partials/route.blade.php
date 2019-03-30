<!-- START_{{$route['id']}} -->
@if($route['title'] != '')## {{ $route['title']}}
@else## {{$route['uri']}}@endif
@if($route['description'])

{!! $route['description'] !!}
@endif @if($route['authenticated'])<br><aside class="notice">Requires authentication.</aside>@endif

@unless(($route['type'] ?? false))
> Example request:

@foreach (config('idoc.language-tabs') as $lang => $name)
```{{$lang}}

@include('idoc::partials.routes.' . $lang, compact('route'))

```
@endforeach

@if(in_array('GET',$route['methods']) || (isset($route['showresponse']) && $route['showresponse']))
@if(is_array($route['response']))
@foreach($route['response'] ?? [] as $response)
> Example response ({{$response['status']}}):

```json
@if(is_object($response['content']) || is_array($response['content']))
{!! json_encode($response['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@else
{!! json_encode(json_decode($response['content']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
```
@endforeach
@else
> Example response:

```json
@if(is_object($route['response']) || is_array($route['response']))
{!! json_encode($route['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@else
{!! json_encode(json_decode($route['response']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
```
@endif
@endif
@endif
<!-- START_{{$route['id']}} -->
@if($route['title'] != '')## {{ $route['title']}}
@else## {{$route['uri']}}@endif
@if($route['description'])

{!! $route['description'] !!}
@endif @if($route['authenticated'])<br><aside class="notice">Requires authentication.</aside>@endif

@unless(($route['type'] ?? false))
> Example request:

@foreach (config('idoc.language-tabs') as $lang => $name)
```{{$lang}}

@include('idoc::partials.routes.' . $lang, compact('route'))

```
@endforeach

@if(in_array('GET',$route['methods']) || (isset($route['showresponse']) && $route['showresponse']))
@if(is_array($route['response']))
@foreach($route['response'] ?? []  as $response)
> Example response ({{$response['status']}}):

```json
@if(is_object($response['content']) || is_array($response['content']))
{!! json_encode($response['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@else
{!! json_encode(json_decode($response['content']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
```
@endforeach
@else
> Example response:

```json
@if(is_object($route['response']) || is_array($route['response']))
{!! json_encode($route['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@else
{!! json_encode(json_decode($route['response']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
```
@endif
@endif
@endif

@if(count($route['pathParameters'] ?? []))
#### Path Parameters

Parameter | Type  | Description
--------- | ------- | -------
@foreach($route['pathParameters'] ?? []  as $attribute => $parameter)
{{$attribute}} | {{$parameter['type']}} | {!! $parameter['description'] !!}
@endforeach
@endif


@if(count($route['queryParameters']))
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
@foreach($route['queryParameters'] ?? []  as $attribute => $parameter)
    {{$attribute}} | @if($parameter['required']) required @else optional @endif | {!! $parameter['description'] !!}
@endforeach
@endif

@if(count($route['bodyParameters']))
#### Body Parameters

Parameter | Type | Status | Description
--------- | -----| ------ | -----------
@foreach($route['bodyParameters'] ?? []  as $attribute => $parameter)
@if($attribute === '-') | <td colspan="4"> {!! $parameter['description'] !!} </td> @else {{$attribute}} | {{$parameter['type']}} | @if($parameter['required']) required @else optional @endif | {!! $parameter['description'] !!}
@endif
@endforeach
@endif

<!-- END_{{$route['id']}} -->




@if(count($route['pathParameters']))
#### Path Parameters

Parameter | Type  | Description
--------- | ------- | -------
@foreach($route['pathParameters'] ?? []  as $attribute => $parameter)
{{$attribute}} | {{$parameter['type']}} | {!! $parameter['description'] !!}
@endforeach
@endif


@if(count($route['queryParameters']))
#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
@foreach($route['queryParameters'] ?? [] as $attribute => $parameter)
    {{$attribute}} | @if($parameter['required']) required @else optional @endif | {!! $parameter['description'] !!}
@endforeach
@endif

@if(count($route['bodyParameters']))
#### Body Parameters

Parameter | Type | Status | Description
--------- | -----| ------ | -----------
@foreach($route['bodyParameters'] ?? []  as $attribute => $parameter)
@if($attribute === '-') | <td colspan="4"> {!! $parameter['description'] !!} </td> @else {{$attribute}} | {{$parameter['type']}} | @if($parameter['required']) required @else optional @endif | {!! $parameter['description'] !!}
@endif
@endforeach
@endif

<!-- END_{{$route['id']}} -->
