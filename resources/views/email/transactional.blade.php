<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transactional Email</title>

    <!-- Styles -->
		<style>
			body {
				font-family: 'Helvetica', 'Arial', sans-serif;
			}
			a {
				color:#112f6f;
			}
			a:hover, a:active, a:focus {
				text-decoration: none;
				color: #2c9dc5;
			}
			table {
				font-family: arial, sans-serif;
				border-collapse: collapse;
				width: 100%;
			}

			td, th {
				border: 1px solid #dddddd;
				text-align: left;
				padding: 8px;
			}

			tr:nth-child(even) {
				background-color: #dddddd;
			}
		</style>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
    <div style="width:100%;max-width:800px;margin:50px auto 50px auto;">
		<div style="padding:50px;background:#fafafa">
				<div style="text-align:center;margin-bottom:50px;">
					<img src="{{asset('/img/logo.svg')}}" style="display:inline-block; width:329px;height:auto;"> 
				</div>
				<div style="font-color:#112f6f;font-size:18px;font-family: 'Helvetica', 'Arial', sans-serif;">
                    <div style="text-align:left;">
                        <h1>{!! $customMessage['headline'] !!}</h1>
                    </div>
                    <p>{!! $customMessage['message'] !!}</p>
                    @if(isset($customMessage['tables']))
						@foreach ($customMessage['tables'] as $table)
						<table>
							<thead>
								<tr>
									<th>Title</th>
									<th>MN Number</th>
									<th>Deliver By</th>
									<th">Send To</th>
									<th>Due Date</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>{!! $table['asset_title'] !!}</td>
									<td>{!! $table['mn_number'] !!}</td>
									<td>{!! $table['deliver_by'] !!}</td>
									<td>{!! $table['receiver'] !!}</td>
									<td>{!! $table['due_date'] !!}</td>
								</tr>
							</tbody>
						</table>
						@endforeach
                    @endif()
                    @if(isset($customMessage['button']) && $customMessage['button'] == true)
                    <a href="{{$customMessage['buttonLink']}}" style="background:#2c9dc5;color:white;padding:15px 25px;font-size:18px;font-family: 'Helvetica', 'Arial', sans-serif;text-decoration:none;display:inline-block;margin:30px 0px;">{!! $customMessage['buttonText'] !!}</a>
                    @endif()
				</div>
			</div>
			<div style="text-align:center;font-color:#b5b5b5;font-size:15px;font-family: 'Helvetica', 'Arial', sans-serif;display:block;padding-top:20px;">
				<p>Copyright &copy; {{date('Y')}} {{env('COMPANY_NAME')}}. All rights reserved.</p>
			</div>
		</div>
  </body>
</html>
