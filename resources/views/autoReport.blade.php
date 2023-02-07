<table>
    <thead>
      <tr>
      @foreach($assets as $asset)
        @foreach($asset->toArray() as $key => $value)
          <th>{{$key}}</th>
        @endforeach
      @endforeach
      </tr>
    </thead>
    <tbody>
    @foreach($assets as $asset)
        <tr>
          @foreach($asset->toArray() as $key => $value)
            <td>{{$value}}</td>
          @endforeach
        </tr>
    @endforeach
    </tbody>
</table>