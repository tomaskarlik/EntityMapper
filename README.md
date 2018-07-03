# EntityMapper

Install
-------
Download package
```
composer require tomaskarlik/entitymapper
````

Register extension in config.neon
```
extensions:
	entityMapper: TomasKarlik\EntityMapper\DI\EntityMapperExtension
```

Extension configuration:
```
entityMapper:
	directory: %appDir%/Model/Entity	# entites root directory
	password: mySecretPassword			# password for encrypted fields (optional)
	namespace: App\Model\Entity			# NS of entites root directory
	namespaces:
		App\Model\Entity\User:		# entity namespace group
			- user					# DB table
			- user_group
		\Work:						# relative NS
			- job
```

Create entity:
```console
php console entity:create-entity user
```
